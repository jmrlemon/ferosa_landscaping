<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantModel extends Model
{
    protected $fillable = [
        'product_id',
        'file_path',
        'file_name',
        'file_size',
        'height_cm',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'height_cm' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
