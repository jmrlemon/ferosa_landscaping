<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded change to a product's stock level.
 *
 * Every write to `products.stock_qty` goes through InventoryService, which writes
 * one of these rows in the same transaction. `quantity` is signed and
 * `quantity_after` snapshots the resulting level, so the history answers "why is
 * stock 42?" without replaying the whole table.
 */
class StockMovement extends Model
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_RESTOCK = 'restock';

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN = 'return';

    public const TYPE_WASTAGE = 'wastage';

    public const TYPE_CORRECTION = 'correction';

    /**
     * Types an admin may record by hand. Sales and returns are written by the
     * order flow, never typed in.
     */
    public const MANUAL_TYPES = [
        self::TYPE_RESTOCK,
        self::TYPE_WASTAGE,
        self::TYPE_CORRECTION,
    ];

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'quantity_after',
        'unit_cost',
        'supplier',
        'reference',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_OPENING => 'Opening balance',
            self::TYPE_RESTOCK => 'Restock',
            self::TYPE_SALE => 'Sale',
            self::TYPE_RETURN => 'Return',
            self::TYPE_WASTAGE => 'Wastage',
            self::TYPE_CORRECTION => 'Correction',
            default => ucfirst((string) $this->type),
        };
    }

    /**
     * Total value of stock received, used for the restock cost column.
     */
    public function totalCost(): ?float
    {
        return $this->unit_cost === null
            ? null
            : (float) $this->unit_cost * abs($this->quantity);
    }
}
