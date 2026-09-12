<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    protected $fillable = [
        'name',
        'default_fee',
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
