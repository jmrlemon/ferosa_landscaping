<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A single payment received against an order or an appointment.
 *
 * The ledger is append-only: a mistake is voided, never deleted, so the billing
 * history a customer was shown can always be reconstructed.
 *
 * Larastan reads `$casts` but not the `casts()` method this model uses, so the
 * datetime attributes below look like plain strings to static analysis.
 *
 * @property Carbon $paid_at
 * @property Carbon|null $voided_at
 */
class Payment extends Model
{
    public const METHODS = ['cash', 'gcash', 'bank_transfer', 'other'];

    protected $fillable = [
        'payable_type',
        'payable_id',
        'amount',
        'method',
        'reference',
        'notes',
        'paid_at',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
