<?php

namespace App\Models;

use App\Support\ProfanityFilter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read string|null $censored_comment */
class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'order_id',
        'appointment_id',
        'product_id',
        'service_type_id',
        'rating',
        'comment',
    ];

    /** @return Attribute<string|null, never> */
    protected function censoredComment(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $comment = $attributes['comment'] ?? null;

                return ProfanityFilter::censor(is_string($comment) ? $comment : null);
            }
        );
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
