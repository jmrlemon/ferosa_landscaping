<?php

namespace App\Models\Concerns;

use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared billing surface for the two things a customer can be charged for:
 * an Order (products) and an Appointment (a service visit).
 *
 * The amounts live in the `payments` ledger; these accessors read it so views
 * and controllers never sum payments by hand.
 */
trait HasPayments
{
    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Payments that still count, newest first - what the invoice lists.
     *
     * Voided rows are excluded here, in one place, so no caller has to remember
     * that the ledger is append-only.
     *
     * @return MorphMany<Payment, $this>
     */
    public function activePayments(): MorphMany
    {
        return $this->payments()->whereNull('voided_at')->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function totalBilled(): float
    {
        return app(BillingService::class)->totalBilled($this);
    }

    public function totalPaid(): float
    {
        return app(BillingService::class)->totalPaid($this);
    }

    public function balanceDue(): float
    {
        return app(BillingService::class)->balanceDue($this);
    }

    public function isFullySettled(): bool
    {
        return $this->totalBilled() > 0 && $this->balanceDue() <= 0;
    }

    /**
     * Invoice numbers are derived from the primary key rather than stored, so
     * they cannot drift from the record. The letter keeps orders and
     * appointments in separate series.
     */
    public function invoiceNumber(): string
    {
        return 'INV-'.$this->invoiceSeriesLetter().str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    abstract protected function invoiceSeriesLetter(): string;
}
