<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequest;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvidence;
use App\Models\User;
use App\Notifications\WorkCreatedNotice;
use App\Services\ReturnRequestService;
use App\Services\ReturnResolutionService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnRequestController extends Controller
{
    public function create(Request $request, Order $order, ReturnRequestService $returns): View
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->canOpenReturnRequest(), 404);
        $order->load(['orderItems', 'returnRequests.items']);

        return view('returns.create', [
            'order' => $order,
            'remainingQuantities' => $returns->remainingQuantities($order),
        ]);
    }

    public function store(
        StoreReturnRequest $request,
        Order $order,
        ReturnRequestService $returns
    ): RedirectResponse {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $data = $request->validated();
        /** @var User $customer */
        $customer = $request->user();
        /** @var array<int, UploadedFile> $evidenceFiles */
        $evidenceFiles = $request->file('evidence', []);
        $claim = $returns->submit($order, $customer, $data, $evidenceFiles);

        Audit::log($request, 'return_request.submit', $claim, null, Audit::snapshot($claim, [
            'claim_number', 'order_id', 'status', 'submitted_at',
        ]));

        Log::info('return_request_submitted', [
            'claim_id' => $claim->id,
            'order_id' => $order->id,
            'item_count' => $claim->items->count(),
            'evidence_count' => $claim->evidence->count(),
        ]);

        try {
            $team = User::query()->whereIn('role', ['admin', 'staff'])->get();
            if ($team->isNotEmpty()) {
                Notification::send($team, new WorkCreatedNotice(
                    type: 'return_request',
                    message: "Damage claim {$claim->claim_number} was submitted for order {$order->order_number}.",
                    url: route('admin.returns.show', $claim, absolute: false),
                    orderId: $order->id,
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('returns.show', $claim)
            ->with('status', 'Your claim was submitted. Ferosa will review the photos and update you here.');
    }

    public function show(Request $request, ReturnRequest $returnRequest): View
    {
        $user = $request->user();
        abort_unless((int) $returnRequest->user_id === (int) $user->id || $user->isStaffOrAdmin(), 403);
        if ($user->isStaff() && ! $user->isAdmin()) {
            $returnRequest->loadMissing('order');
            abort_if($returnRequest->order->archived_at !== null, 404);
        }
        $returnRequest->load(['order', 'items.orderItem', 'evidence', 'refunds']);

        return view('returns.show', ['claim' => $returnRequest]);
    }

    public function evidence(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnRequestEvidence $evidence
    ): StreamedResponse {
        abort_unless((int) $evidence->return_request_id === (int) $returnRequest->id, 404);

        $user = $request->user();
        abort_unless(
            (int) $returnRequest->user_id === (int) $user->id || $user->isStaffOrAdmin(),
            403
        );
        if ($user->isStaff() && ! $user->isAdmin()) {
            $returnRequest->loadMissing('order');
            abort_if($returnRequest->order->archived_at !== null, 404);
        }
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->response(
            $evidence->path,
            $evidence->original_name,
            ['Content-Type' => $evidence->mime_type]
        );
    }

    public function information(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns
    ): RedirectResponse {
        abort_unless((int) $returnRequest->user_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'customer_contact_notes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        /** @var User $customer */
        $customer = $request->user();
        $claim = $returns->customerReply($returnRequest, $customer, $data['customer_contact_notes']);
        Audit::log($request, 'return_request.information', $claim, null, Audit::snapshot($claim, [
            'status', 'customer_contact_notes',
        ]));
        try {
            $team = User::query()->whereIn('role', ['admin', 'staff'])->get();
            if ($team->isNotEmpty()) {
                Notification::send($team, new WorkCreatedNotice(
                    type: 'return_request',
                    message: "Customer information was added to claim {$claim->claim_number}.",
                    url: route('admin.returns.show', $claim, absolute: false),
                    orderId: $claim->order_id,
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('returns.show', $claim)->with('status', 'Your additional information was sent.');
    }

    public function cancel(
        Request $request,
        ReturnRequest $returnRequest,
        ReturnResolutionService $returns
    ): RedirectResponse {
        abort_unless((int) $returnRequest->user_id === (int) $request->user()->id, 403);
        /** @var User $customer */
        $customer = $request->user();
        $claim = $returns->cancel($returnRequest, $customer);
        Audit::log($request, 'return_request.cancel', $claim, null, Audit::snapshot($claim, [
            'status', 'resolved_at',
        ]));

        return redirect()->route('returns.show', $claim)->with('status', 'Your claim was cancelled.');
    }
}
