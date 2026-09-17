<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity_claimed',
        'issue_type',
        'issue_description',
        'preferred_resolution',
        'resolution',
        'replacement_quantity',
        'refund_amount',
        'decision_note',
        'disposition',
        'restocked_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity_claimed' => 'integer',
            'replacement_quantity' => 'integer',
            'refund_amount' => 'decimal:2',
            'restocked_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function maximumRefundAmount(): float
    {
        return round((float) $this->orderItem->price * $this->quantity_claimed, 2);
    }
}
