<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the money side of orders and appointments.
 *
 * The `payments` ledger is the source of truth for how much has been received.
 * `payment_status` is a cached summary of that ledger and is never set by hand
 * except for the proof-review states (pending_verification / rejected) and
 * refunded, which describe a review decision rather than an amount.
 */
class BillingService
{
    /**
     * Record a payment and refresh the payable's cached payment_status.
     *
     * @param  Order|Appointment  $payable
     */
    public function record(Model $payable, array $attributes): Payment
    {
        return DB::transaction(function () use ($payable, $attributes): Payment {
            $payment = $payable->payments()->create([
                'amount' => round((float) $attributes['amount'], 2),
                'method' => $attributes['method'] ?? 'cash',
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? now(),
                'recorded_by' => $attributes['recorded_by'] ?? null,
            ]);

            $this->syncPaymentStatus($payable);

            return $payment;
        }, 3);
    }

    /**
     * Total billed for the record - the figure the invoice is drawn against.
     *
     * @param  Order|Appointment  $payable
     */
    public function totalBilled(Model $payable): float
    {
        return round((float) ($payable instanceof Order
            ? $payable->total_amount
            : $payable->appointment_amount), 2);
    }

    /**
     * @param  Order|Appointment  $payable
     */
    public function totalPaid(Model $payable): float
    {
        return round((float) $payable->activePayments()->sum('amount'), 2);
    }

    /**
     * Never negative: an overpayment shows as a zero balance, not a credit.
     *
     * @param  Order|Appointment  $payable
     */
    public function balanceDue(Model $payable): float
    {
        return round(max(0, $this->totalBilled($payable) - $this->totalPaid($payable)), 2);
    }

    public function totalRefunded(Order $order): float
    {
        return round((float) $order->activeRefunds()->sum('amount'), 2);
    }

    public function netPaid(Order $order): float
    {
        return round(max(0, $this->totalPaid($order) - $this->totalRefunded($order)), 2);
    }

    public function refundableAmount(Order $order): float
    {
        return $this->netPaid($order);
    }

    /**
     * Record money returned without corrupting the payments-received ledger.
     *
     * @param  array{amount: float|int|string, method?: string, reference?: string|null, notes?: string|null, refunded_at?: mixed, processed_by?: int|null}  $attributes
     */
    public function recordRefund(Order $order, ReturnRequest $claim, array $attributes): Refund
    {
        return DB::transaction(function () use ($order, $claim, $attributes): Refund {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            /** @var ReturnRequest $lockedClaim */
            $lockedClaim = ReturnRequest::query()->lockForUpdate()->findOrFail($claim->id);
            abort_unless((int) $lockedClaim->order_id === (int) $lockedOrder->id, 422);
            abort_unless(in_array($lockedClaim->status, ['approved', 'replacement_dispatched', 'resolved'], true), 422);

            $amount = round((float) $attributes['amount'], 2);
            $approved = round((float) $lockedClaim->items()->sum('refund_amount'), 2);
            $alreadyClaimed = round((float) $lockedClaim->refunds()->whereNull('voided_at')->sum('amount'), 2);
            $maximum = round(min(
                max(0, $approved - $alreadyClaimed),
                $this->refundableAmount($lockedOrder)
            ), 2);

            if ($amount <= 0 || $amount > $maximum) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount cannot exceed ₱'.number_format($maximum, 2).'.',
                ]);
            }

            $refund = $lockedOrder->refunds()->create([
                'return_request_id' => $lockedClaim->id,
                'amount' => $amount,
                'method' => $attributes['method'] ?? 'cash',
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'refunded_at' => $attributes['refunded_at'] ?? now(),
                'processed_by' => $attributes['processed_by'] ?? null,
            ]);

            if ($this->totalRefunded($lockedOrder) >= $this->totalPaid($lockedOrder)
                && $this->totalPaid($lockedOrder) > 0) {
                $lockedOrder->forceFill(['payment_status' => 'refunded'])->save();
            }

            return $refund;
        }, 3);
    }

    /**
     * Derive payment_status from the ledger.
     *
     * `refunded` is a manual decision and is left alone. `pending_verification`
     * and `rejected` describe a GCash proof under review, so they survive while
     * nothing has actually been received.
     *
     * @param  Order|Appointment  $payable
     */
    public function syncPaymentStatus(Model $payable): string
    {
        $current = (string) ($payable->payment_status ?? 'unpaid');

        if ($current === 'refunded') {
            return $current;
        }

        $paid = $this->totalPaid($payable);
        $billed = $this->totalBilled($payable);

        $status = match (true) {
            $paid <= 0 => in_array($current, ['pending_verification', 'rejected'], true) ? $current : 'unpaid',
            $billed > 0 && $paid >= $billed => 'paid',
            default => 'partial',
        };

        if ($status !== $current) {
            $updates = ['payment_status' => $status];

            // Orders carry a verification stamp; appointments do not.
            if ($payable instanceof Order) {
                if ($status === 'paid') {
                    $updates['payment_verified_at'] = $payable->payment_verified_at ?? now();
                } elseif ($status === 'unpaid') {
                    $updates['payment_verified_at'] = null;
                    $updates['payment_verified_by'] = null;
                }
            }

            $payable->forceFill($updates)->save();
        }

        return $status;
    }

    /**
     * Settle whatever is outstanding in one entry.
     *
     * This is what "mark as paid" in the admin workspace now does, so the status
     * and the ledger can never disagree.
     *
     * @param  Order|Appointment  $payable
     */
    public function settle(Model $payable, ?int $userId, string $method = 'cash', ?string $notes = null): ?Payment
    {
        $balance = $this->balanceDue($payable);

        if ($balance <= 0) {
            $this->syncPaymentStatus($payable);

            return null;
        }

        return $this->record($payable, [
            'amount' => $balance,
            'method' => $method,
            'notes' => $notes ?? 'Balance settled from the admin workspace.',
            'recorded_by' => $userId,
        ]);
    }
}
