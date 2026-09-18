<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ReturnRequestNotifier;
use App\Services\ReturnResolutionService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AdminReturnRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $claims = ReturnRequest::query()
            ->with(['user', 'order'])
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->whereHas(
                'order',
                fn ($orderQuery) => $orderQuery->whereNull('archived_at')
            ))
            ->when(in_array($status, ReturnRequest::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status IN ('submitted', 'needs_information') THEN 0 ELSE 1 END")
            ->orderBy('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.returns.index', [
            'claims' => $claims,
            'selectedStatus' => $status,
        ]);
    }

    public function show(ReturnRequest $returnRequest, BillingService $billing): View
    {
        $this->assertAccessible($returnRequest);
        $returnRequest->load([
            'user',
            'order.payments',
            'order.refunds',
            'items.orderItem.product',
            'evidence',
            'refunds.processedBy',
            'reviewedBy',
            'decidedBy',
        ]);

        $refundableAmount = $billing->refundableAmount($returnRequest->order);
        $approvedRefundRemaining = round(max(
            0,
            (float) $returnRequest->items->sum('refund_amount')
                - (float) $returnRequest->refunds->whereNull('voided_at')->sum('amount')
        ), 2);
        $maximumRefundAmount = round(min($approvedRefundRemaining, $refundableAmount), 2);

        return view('admin.returns.show', [
            'claim' => $returnRequest,
            'totalPaid' => $billing->totalPaid($returnRequest->order),
            'totalRefunded' => $billing->totalRefunded($returnRequest->order),
            'refundableAmount' => $refundableAmount,
            'approvedRefundRemaining' => $approvedRefundRemaining,
            'maximumRefundAmount' => $maximumRefundAmount,
            'isAdmin' => auth()->user()?->isAdmin() ?? false,
        ]);
    }

    public function review(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns,
        ReturnRequestNotifier $notifier,
    ): RedirectResponse {
        $this->assertAccessible($returnRequest);
        $data = $request->validate([
            'action' => ['required', Rule::in(['under_review', 'needs_information'])],
            'decision_reason' => [
                Rule::requiredIf($request->input('action') === 'needs_information'),
                'nullable', 'string', 'max:1000',
            ],
        ]);
        $before = Audit::snapshot($returnRequest, ['status', 'decision_reason']);
        /** @var User $actor */
        $actor = $request->user();
        $claim = $returns->review($returnRequest, $actor, $data['action'], $data['decision_reason'] ?? null);
        Audit::log($request, 'return_request.review', $claim, $before, Audit::snapshot($claim, [
            'status', 'decision_reason', 'reviewed_by', 'reviewed_at',
        ]));
        if ($claim->status === 'needs_information') {
            $notifier->notify($claim, 'needs_information');
        }

        return redirect()->route('admin.returns.show', $claim)->with('status', 'Claim review status updated.');
    }

    public function decision(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns,
        ReturnRequestNotifier $notifier,
    ): RedirectResponse {
        $this->assertAccessible($returnRequest);
        $data = $request->validate([
            'decision_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'return_required' => ['nullable', 'boolean'],
            'return_instructions' => [
                Rule::requiredIf($request->boolean('return_required')),
                'nullable', 'string', 'max:1000',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.resolution' => ['required', Rule::in(ReturnRequest::RESOLUTION_TYPES)],
            'items.*.replacement_quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.refund_amount' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.decision_note' => ['nullable', 'string', 'max:500'],
        ]);
        $before = Audit::snapshot($returnRequest, ['status', 'decision_reason']);
        /** @var User $admin */
        $admin = $request->user();

        try {
            $claim = $returns->decide(
                $returnRequest,
                $admin,
                $data['items'],
                $data['decision_reason'],
                $request->boolean('return_required'),
                $data['return_instructions'] ?? null,
                $data['admin_notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        Audit::log($request, 'return_request.decision', $claim, $before, Audit::snapshot($claim, [
            'status', 'decision_reason', 'decided_by', 'decided_at', 'return_required',
        ]));
        $notifier->notify($claim, $claim->status);

        return redirect()->route('admin.returns.show', $claim)->with('status', 'Claim decision saved.');
    }

    public function dispatch(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns,
        ReturnRequestNotifier $notifier,
    ): RedirectResponse {
        $this->assertAccessible($returnRequest);
        $data = $request->validate([
            'replacement_driver_name' => ['required', 'string', 'max:120'],
            'replacement_driver_phone' => ['nullable', 'string', 'max:30'],
            'replacement_dispatch_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = Audit::snapshot($returnRequest, ['status', 'replacement_dispatched_at']);
        $claim = $returns->dispatch($returnRequest, $data);
        Audit::log($request, 'return_request.dispatch', $claim, $before, Audit::snapshot($claim, [
            'status', 'replacement_driver_name', 'replacement_dispatched_at',
        ]));
        $notifier->notify($claim, 'replacement_dispatched');

        return redirect()->route('admin.returns.show', $claim)->with('status', 'Replacement marked out for delivery.');
    }

    public function resolve(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns,
        ReturnRequestNotifier $notifier,
    ): RedirectResponse {
        $this->assertAccessible($returnRequest);
        $request->validate(['replacement_delivered' => ['nullable', 'boolean']]);
        $before = Audit::snapshot($returnRequest, ['status', 'resolved_at']);
        $claim = $returns->resolve($returnRequest, $request->boolean('replacement_delivered'));
        Audit::log($request, 'return_request.resolve', $claim, $before, Audit::snapshot($claim, [
            'status', 'resolved_at', 'replacement_delivered_at',
        ]));
        $notifier->notify($claim, 'resolved');

        return redirect()->route('admin.returns.show', $claim)->with('status', 'Claim resolved.');
    }

    public function refund(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns,
        ReturnRequestNotifier $notifier,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(Refund::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['processed_by'] = $request->user()->id;
        $refund = $returns->refund($returnRequest, $data);
        Audit::log($request, 'return_request.refund', $refund, null, Audit::snapshot($refund, [
            'order_id', 'return_request_id', 'amount', 'method', 'refunded_at', 'processed_by',
        ]));
        $returnRequest->refresh();
        $notifier->notify($returnRequest, 'refund');
        if ($returnRequest->status === 'resolved') {
            $notifier->notify($returnRequest, 'resolved');
        }

        return redirect()->route('admin.returns.show', $returnRequest)->with('status', 'Refund recorded.');
    }

    public function restock(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnRequestItem $item,
        ReturnResolutionService $returns,
    ): RedirectResponse {
        abort_unless((int) $item->return_request_id === (int) $returnRequest->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        /** @var User $admin */
        $admin = $request->user();
        $updated = $returns->restock($item, $admin, (int) $data['quantity']);
        Audit::log($request, 'return_request.restock', $updated, null, Audit::snapshot($updated, [
            'return_request_id', 'order_item_id', 'disposition', 'restocked_quantity',
        ]));

        return redirect()->route('admin.returns.show', $returnRequest)->with('status', 'Inspected item returned to saleable stock.');
    }

    public function voidRefund(
        Request $request,
        ReturnRequest $returnRequest,
        Refund $refund,
        ReturnResolutionService $returns,
    ): RedirectResponse {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        /** @var User $admin */
        $admin = $request->user();
        $before = Audit::snapshot($refund, ['voided_at', 'voided_by', 'void_reason']);
        $returns->voidRefund($returnRequest, $refund, $admin, $data['void_reason']);
        $refund->refresh();
        Audit::log($request, 'return_request.refund.void', $refund, $before, Audit::snapshot($refund, [
            'voided_at', 'voided_by', 'void_reason',
        ]));

        return redirect()->route('admin.returns.show', $returnRequest)->with('status', 'Refund entry voided; its audit history was preserved.');
    }

    private function assertAccessible(ReturnRequest $claim): void
    {
        $claim->loadMissing('order');
        abort_if($claim->order->archived_at !== null && ! auth()->user()?->isAdmin(), 404);
    }
}
