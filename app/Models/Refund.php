<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only record of money returned to a customer.
 *
 * @property Carbon $refunded_at
 * @property Carbon|null $voided_at
 */
class Refund extends Model
{
    public const METHODS = ['cash', 'gcash', 'bank_transfer', 'other'];

    protected $fillable = [
        'order_id',
        'return_request_id',
        'amount',
        'method',
        'reference',
        'notes',
        'refunded_at',
        'processed_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            default => 'Other',
        };
    }
}
