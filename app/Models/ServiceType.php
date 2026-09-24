<?php

namespace App\Models;

use App\Services\PhilippineDiscountCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    public const DISCOUNT_SCHEMES = [
        PhilippineDiscountCalculator::SCHEME_NONE,
        PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
    ];

    protected $fillable = [
        'name',
        'default_fee',
        'discount_scheme',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'default_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function customerPriceLabel(): string
    {
        if ((float) $this->default_fee <= 0) {
            return 'Quote after site assessment';
        }

        return 'From PHP '.number_format((float) $this->default_fee, 0);
    }
}
