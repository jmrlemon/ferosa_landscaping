<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const BENEFICIARY_TYPES = ['senior', 'pwd'];

    protected $fillable = [
        'beneficiary_type',
        'evidence_path',
        'status',
        'id_reference_last4',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $hidden = ['evidence_path', 'id_reference_last4'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function beneficiaryLabel(): string
    {
        return $this->beneficiary_type === 'pwd' ? 'PWD' : 'Senior Citizen';
    }
}
