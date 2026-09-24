<?php

namespace App\Models;

use App\Services\PhilippineDiscountCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountApplication extends Model
{
    public const STATUS_APPROVED = 'approved';

    public const STATUS_VOIDED = 'voided';

    public const BENEFICIARY_TYPES = ['senior', 'pwd'];

    protected $fillable = [
        'discount_request_id',
        'scheme',
        'beneficiary_type',
        'id_reference_last4',
        'status',
        'gross_total',
        'eligible_gross',
        'vat_removed',
        'discount_base',
        'discount_rate',
        'discount_amount',
        'net_total',
        'legal_basis_version',
        'metadata',
        'verified_by',
        'verified_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $hidden = ['id_reference_last4'];

    protected function casts(): array
    {
        return [
            'gross_total' => 'decimal:2',
            'eligible_gross' => 'decimal:2',
            'vat_removed' => 'decimal:2',
            'discount_base' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_total' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<DiscountRequest, $this> */
    public function discountRequest(): BelongsTo
    {
        return $this->belongsTo(DiscountRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function schemeLabel(): string
    {
        return match ($this->scheme) {
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20 => '20% Senior/PWD statutory discount',
            PhilippineDiscountCalculator::SCHEME_BNPC_5 => '5% BNPC special discount',
            default => 'Discount',
        };
    }

    public function beneficiaryLabel(): string
    {
        return $this->beneficiary_type === 'pwd' ? 'PWD' : 'Senior Citizen';
    }
}
