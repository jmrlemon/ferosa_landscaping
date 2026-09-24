<?php

namespace App\Models;

use App\Services\PhilippineDiscountCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @var list<string> */
    public const AREA_COVERAGE_CATEGORIES = ['grass', 'stones'];

    /** @var list<string> */
    public const DISCOUNT_SCHEMES = [
        PhilippineDiscountCalculator::SCHEME_NONE,
        PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        PhilippineDiscountCalculator::SCHEME_BNPC_5,
    ];

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'price',
        'discount_scheme',
        'stock_qty',
        'sale_unit',
        'coverage_sqm_per_unit',
        'coverage_waste_percent',
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
            'coverage_sqm_per_unit' => 'decimal:4',
            'coverage_waste_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function inStock(): bool
    {
        return $this->stock_qty > 0;
    }

    public function supportsAreaCoverage(): bool
    {
        return self::categorySupportsAreaCoverage($this->category)
            && $this->sale_unit !== null
            && $this->sale_unit !== ''
            && $this->coverage_sqm_per_unit !== null
            && (float) $this->coverage_sqm_per_unit > 0;
    }

    public static function categorySupportsAreaCoverage(?string $category): bool
    {
        return in_array(
            strtolower(trim((string) $category)),
            self::AREA_COVERAGE_CATEGORIES,
            true,
        );
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
