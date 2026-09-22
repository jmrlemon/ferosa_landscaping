<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnResolutionService
{
    public function __construct(
        private InventoryService $inventory,
        private BillingService $billing,
    ) {}

    public function review(ReturnRequest $claim, User $actor, string $action, ?string $reason): ReturnRequest
    {
        return DB::transaction(function () use ($claim, $actor, $action, $reason): ReturnRequest {
            $locked = $this->locked($claim);
            $next = $action === 'needs_information' ? 'needs_information' : 'under_review';
            $this->transition($locked, $next);
            $locked->forceFill([
                'status' => $next,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
                'decision_reason' => $next === 'needs_information' ? $reason : $locked->decision_reason,
            ])->save();

            return $locked;
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     */
    public function decide(
        ReturnRequest $claim,
        User $admin,
        array $decisions,
        string $reason,
        bool $returnRequired = false,
        ?string $returnInstructions = null,
        ?string $adminNotes = null,
    ): ReturnRequest {
        return DB::transaction(function () use (
            $claim,
            $admin,
            $decisions,
            $reason,
            $returnRequired,
            $returnInstructions,
            $adminNotes
        ): ReturnRequest {
            $locked = $this->locked($claim);
            if (! in_array($locked->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['status' => 'This claim has already been decided.']);
            }

            $items = $locked->items()->with('orderItem.product')->lockForUpdate()->get()->keyBy('id');
            $decisionIds = collect($decisions)->pluck('id')->map(fn (mixed $id): int => (int) $id);
            if ($decisionIds->unique()->count() !== $items->count()
                || $decisionIds->diff($items->keys())->isNotEmpty()
                || $items->keys()->diff($decisionIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Decide every claimed item exactly once.']);
            }

            $hasApprovedOutcome = false;
            foreach ($decisions as $decision) {
                /** @var ReturnRequestItem $item */
                $item = $items->get((int) $decision['id']);
                $resolution = (string) $decision['resolution'];
                $replacementQuantity = $resolution === 'replacement'
                    ? (int) ($decision['replacement_quantity'] ?? 0)
                    : 0;
                $refundAmount = match ($resolution) {
                    'refund' => $item->maximumRefundAmount(),
                    'partial_refund' => round((float) ($decision['refund_amount'] ?? 0), 2),
                    default => 0.0,
                };

                if ($resolution === 'replacement') {
                    if ($replacementQuantity < 1 || $replacementQuantity > $item->quantity_claimed) {
                        throw ValidationException::withMessages(['items' => 'Replacement quantity exceeds the claimed quantity.']);
                    }
                    $product = $item->orderItem?->product;
                    if ($product === null) {
                        throw ValidationException::withMessages(['items' => 'A claimed product is no longer available for replacement.']);
                    }
                    $this->inventory->recordReplacement(
                        $product,
                        $replacementQuantity,
                        $locked->claim_number,
                        $admin->id
                    );
                    $hasApprovedOutcome = true;
                }

                if (in_array($resolution, ['refund', 'partial_refund'], true)) {
                    if ($refundAmount <= 0 || $refundAmount > $item->maximumRefundAmount()) {
                        throw ValidationException::withMessages(['items' => 'A refund exceeds the purchased value of its claimed item.']);
                    }
                    $hasApprovedOutcome = true;
                }

                $item->update([
                    'resolution' => $resolution,
                    'replacement_quantity' => $replacementQuantity,
                    'refund_amount' => $refundAmount,
                    'decision_note' => $decision['decision_note'] ?? null,
                    'disposition' => $returnRequired ? 'awaiting_return' : 'not_required',
                ]);
            }

            $next = $hasApprovedOutcome ? 'approved' : 'rejected';
            if ($locked->status === 'submitted') {
                $this->transition($locked, 'under_review');
                $locked->status = 'under_review';
            }
            $this->transition($locked, $next);
            $locked->forceFill([
                'status' => $next,
                'reviewed_at' => $locked->reviewed_at ?? now(),
                'reviewed_by' => $locked->reviewed_by ?? $admin->id,
                'decided_at' => now(),
                'decided_by' => $admin->id,
                'decision_reason' => $reason,
                'admin_notes' => $adminNotes,
                'return_required' => $returnRequired,
                'return_instructions' => $returnRequired ? $returnInstructions : null,
                'resolved_at' => $next === 'rejected' ? now() : null,
            ])->save();

            return $locked->load(['items.orderItem.product', 'user', 'order']);
        }, 3);
    }

    public function customerReply(ReturnRequest $claim, User $customer, string $notes): ReturnRequest
    {
        return DB::transaction(function () use ($claim, $customer, $notes): ReturnRequest {
            $locked = $this->locked($claim);
            abort_unless((int) $locked->user_id === (int) $customer->id, 403);
            $this->transition($locked, 'submitted');
            $locked->update([
                'status' => 'submitted',
                'customer_contact_notes' => $notes,
            ]);

            return $locked;
        }, 3);
    }

    public function cancel(ReturnRequest $claim, User $customer): ReturnRequest
    {
        return DB::transaction(function () use ($claim, $customer): ReturnRequest {
            $locked = $this->locked($claim);
            abort_unless((int) $locked->user_id === (int) $customer->id, 403);
            $this->transition($locked, 'cancelled');
            $locked->update(['status' => 'cancelled', 'resolved_at' => now()]);

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $details */
    public function dispatch(ReturnRequest $claim, array $details): ReturnRequest
    {
        return DB::transaction(function () use ($claim, $details): ReturnRequest {
            $locked = $this->locked($claim);
            abort_unless($locked->items()->where('replacement_quantity', '>', 0)->exists(), 422);
            $this->transition($locked, 'replacement_dispatched');
            $locked->forceFill([
                'status' => 'replacement_dispatched',
                'replacement_driver_name' => $details['replacement_driver_name'],
                'replacement_driver_phone' => $details['replacement_driver_phone'] ?? null,
                'replacement_dispatch_notes' => $details['replacement_dispatch_notes'] ?? null,
                'replacement_dispatched_at' => now(),
            ])->save();

            return $locked->load(['user', 'order']);
        }, 3);
    }

    public function resolve(ReturnRequest $claim, bool $replacementDelivered = false): ReturnRequest
    {
        return DB::transaction(function () use ($claim, $replacementDelivered): ReturnRequest {
            $locked = $this->locked($claim);
            $replacementQuantity = (int) $locked->items()->sum('replacement_quantity');
            $approvedRefund = round((float) $locked->items()->sum('refund_amount'), 2);
            $recordedRefund = round((float) $locked->refunds()->whereNull('voided_at')->sum('amount'), 2);
            if ($replacementQuantity > 0
                && ($locked->status !== 'replacement_dispatched' || ! $replacementDelivered)) {
                throw ValidationException::withMessages([
                    'status' => 'Confirm the dispatched replacement was delivered before resolving this claim.',
                ]);
            }
            if ($approvedRefund > $recordedRefund) {
                throw ValidationException::withMessages([
                    'status' => 'Record the full approved refund before resolving this claim.',
                ]);
            }
            $this->transition($locked, 'resolved');
            $locked->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'replacement_delivered_at' => $replacementDelivered ? now() : $locked->replacement_delivered_at,
            ])->save();

            return $locked->load(['user', 'order']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function refund(ReturnRequest $claim, array $attributes): Refund
    {
        $refund = $this->billing->recordRefund($claim->order, $claim, $attributes);
        $claim->refresh();
        $approvedRefund = round((float) $claim->items()->sum('refund_amount'), 2);
        $recordedRefund = round((float) $claim->refunds()->whereNull('voided_at')->sum('amount'), 2);
        $hasReplacement = $claim->items()->where('replacement_quantity', '>', 0)->exists();
        if (! $hasReplacement && $approvedRefund > 0 && $recordedRefund >= $approvedRefund) {
            $this->resolve($claim);
        }

        return $refund;
    }

    public function restock(ReturnRequestItem $item, User $admin, int $quantity): ReturnRequestItem
    {
        return DB::transaction(function () use ($item, $admin, $quantity): ReturnRequestItem {
            /** @var ReturnRequestItem $locked */
            $locked = ReturnRequestItem::query()->with(['returnRequest', 'orderItem.product'])
                ->lockForUpdate()->findOrFail($item->id);
            if ($locked->disposition === 'restocked') {
                throw ValidationException::withMessages(['quantity' => 'This item was already restocked.']);
            }
            if ($quantity < 1 || $quantity > $locked->quantity_claimed) {
                throw ValidationException::withMessages(['quantity' => 'Restock quantity exceeds the claimed quantity.']);
            }
            $product = $locked->orderItem?->product;
            if ($product === null) {
                throw ValidationException::withMessages(['quantity' => 'The original product no longer exists.']);
            }
            $this->inventory->recordClaimRestock(
                $product,
                $quantity,
                $locked->returnRequest->claim_number,
                $admin->id
            );
            $locked->update(['disposition' => 'restocked', 'restocked_quantity' => $quantity]);

            return $locked;
        }, 3);
    }

    private function locked(ReturnRequest $claim): ReturnRequest
    {
        return ReturnRequest::query()->lockForUpdate()->findOrFail($claim->id);
    }

    private function transition(ReturnRequest $claim, string $next): void
    {
        if (! $claim->canTransitionTo($next) || $claim->status === $next) {
            throw ValidationException::withMessages(['status' => 'That claim status change is no longer available.']);
        }
    }
}
