<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A post-delivery claim that is deliberately independent from order fulfilment.
 *
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $replacement_estimated_delivery_date
 * @property Carbon|null $replacement_dispatched_at
 * @property Carbon|null $replacement_delivered_at
 */
class ReturnRequest extends Model
{
    public const CLAIM_WINDOW_HOURS = 24;

    public const MAX_EVIDENCE_FILES = 5;

    public const STATUSES = [
        'submitted',
        'under_review',
        'needs_information',
        'approved',
        'replacement_dispatched',
        'resolved',
        'rejected',
        'cancelled',
    ];

    public const STATUS_TRANSITIONS = [
        'submitted' => ['under_review', 'needs_information', 'cancelled'],
        'under_review' => ['needs_information', 'approved', 'rejected'],
        'needs_information' => ['submitted', 'cancelled'],
        'approved' => ['replacement_dispatched', 'resolved'],
        'replacement_dispatched' => ['resolved'],
        'resolved' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public const ISSUE_TYPES = [
        'damaged_on_arrival',
        'unhealthy_on_arrival',
        'wrong_item',
        'missing_quantity',
    ];

    public const PREFERRED_RESOLUTIONS = ['replacement', 'refund'];

    public const RESOLUTION_TYPES = [
        'replacement',
        'refund',
        'partial_refund',
        'rejected',
    ];

    public const DISPOSITIONS = [
        'not_required',
        'awaiting_return',
        'damaged_discard',
        'restocked',
    ];

    protected $fillable = [
        'claim_number',
        'order_id',
        'user_id',
        'status',
        'customer_summary',
        'customer_contact_notes',
        'submitted_at',
        'reviewed_at',
        'decided_at',
        'resolved_at',
        'reviewed_by',
        'decided_by',
        'decision_reason',
        'admin_notes',
        'return_required',
        'return_instructions',
        'replacement_driver_name',
        'replacement_driver_phone',
        'replacement_dispatch_notes',
        'replacement_estimated_delivery_date',
        'replacement_dispatch_proof_path',
        'replacement_dispatched_at',
        'replacement_delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'return_required' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
            'resolved_at' => 'datetime',
            'replacement_estimated_delivery_date' => 'date',
            'replacement_dispatched_at' => 'datetime',
            'replacement_delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ReturnRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    /** @return HasMany<ReturnRequestEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ReturnRequestEvidence::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status
            || in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['resolved', 'rejected', 'cancelled'], true);
    }
}
