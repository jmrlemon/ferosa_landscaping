<?php

namespace App\Models;

use App\Services\BillingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Larastan reads `$casts` but not the `casts()` method this model uses, so the
 * cast attributes below look like plain strings to static analysis. Declare the
 * types the casts actually produce.
 *
 * @property array<int, array<string, mixed>> $items
 * @property Carbon|null $delivered_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $customer_confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $payment_verified_at
 * @property Carbon|null $archived_at
 * @property 'pending'|'confirmed'|'out_for_delivery'|'delivered'|'completed'|'cancelled' $status
 */
class Order extends Model
{
    use Concerns\HasPayments;

    public const FOLLOW_UP_AFTER_DAYS = 7;

    public const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'checkout_token',
        'status',
        'payment_status',
        'total_amount',
        'items',
        'archived_at',
        'delivery_method',
        'delivery_name',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'delivery_notes',
        'payment_method',
        'payment_reference',
        'payment_reference_normalized',
        'payment_proof_path',
        'payment_review_notes',
        'payment_verified_at',
        'payment_verified_by',
        'delivery_proof_url',
        'dispatch_proof_url',
        'dispatched_at',
        'driver_name',
        'driver_phone',
        'dispatch_notes',
        'delivery_recipient_name',
        'delivered_at',
        'customer_confirmed_at',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $hidden = [
        'payment_reference_normalized',
        'payment_proof_path',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_amount' => 'decimal:2',
            'archived_at' => 'datetime',
            'delivered_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<ReturnRequest, $this> */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return HasMany<Refund, $this> */
    public function activeRefunds(): HasMany
    {
        return $this->refunds()->whereNull('voided_at')->orderByDesc('refunded_at')->orderByDesc('id');
    }

    public function totalRefunded(): float
    {
        if ($this->relationLoaded('activeRefunds')) {
            return round((float) $this->activeRefunds->sum('amount'), 2);
        }

        return app(BillingService::class)->totalRefunded($this);
    }

    public function netPaid(): float
    {
        return app(BillingService::class)->netPaid($this);
    }

    /** @return HasOne<Feedback, $this> */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function paymentVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public static function normalizePaymentReference(?string $reference): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $reference));

        return $normalized !== '' ? $normalized : null;
    }

    /** Customers may cancel only while the order is awaiting team review. */
    public function isCustomerCancellable(): bool
    {
        return $this->status === 'pending';
    }

    /** Open orders older than a week should not look current without explanation. */
    public function needsStatusFollowUp(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true)
            && $this->created_at !== null
            && $this->created_at->lte(Carbon::now()->subDays(self::FOLLOW_UP_AFTER_DAYS));
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status
            || in_array($status, self::STATUS_TRANSITIONS[$this->status], true);
    }

    public function returnClaimDeadline(): ?Carbon
    {
        $receivedAt = $this->customer_confirmed_at ?? $this->delivered_at;

        return $receivedAt?->copy()->addHours(ReturnRequest::CLAIM_WINDOW_HOURS);
    }

    public function canOpenReturnRequest(): bool
    {
        $deadline = $this->returnClaimDeadline();

        if ($this->archived_at !== null
            || ! in_array($this->status, ['delivered', 'completed'], true)
            || $deadline === null
            || now()->gt($deadline)) {
            return false;
        }

        $hasResolvedClaim = $this->relationLoaded('returnRequests')
            ? $this->returnRequests->contains('status', 'resolved')
            : $this->returnRequests()->where('status', 'resolved')->exists();

        return ! $hasResolvedClaim;
    }

    public function hasFinalReceipt(): bool
    {
        return $this->status === 'completed';
    }

    protected function invoiceSeriesLetter(): string
    {
        return 'O';
    }
}
