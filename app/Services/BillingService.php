<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Void a payment recorded in error. The row survives; it stops counting.
     *
     * @param  Order|Appointment  $payable
     */
    public function void(Model $payable, Payment $payment, ?int $userId, ?string $reason): void
    {
        DB::transaction(function () use ($payable, $payment, $userId, $reason): void {
            if ($payment->isVoided()) {
                return;
            }

            $payment->update([
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);

            $this->syncPaymentStatus($payable);
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
