<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_url',
        'price',
        'stock_qty',
        'category',
        'is_active',
        'archived_at',
    ];

    protected $appends = ['is_ar_enabled'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function inStock(): bool
    {
        return $this->stock_qty > 0;
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<PlantModel, $this>
     */
    public function plantModel(): HasOne
    {
        return $this->hasOne(PlantModel::class);
    }

    /**
     * Stock history, newest first. Written only by InventoryService.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Scope to filter only products that have an associated AR plant model.
     */
    public function scopeArEnabled(Builder $query): Builder
    {
        return $query->whereHas('plantModel');
    }

    /**
     * Determine if the product has an associated AR plant model.
     */
    public function getIsArEnabledAttribute(): bool
    {
        return $this->plantModel()->exists();
    }
}
