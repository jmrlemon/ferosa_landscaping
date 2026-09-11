<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Values already read during this request, keyed by setting name.
     *
     * Settings are read from layouts and partials that render many times per
     * page — getBusinessProfile() alone is nine reads — which put 12-21
     * identical queries on every admin page. They change rarely enough that one
     * read per request is plenty.
     *
     * @var array<string, string|null>
     */
    private static array $memo = [];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, static::$memo)) {
            static::$memo[$key] = static::query()->where('key', $key)->value('value');
        }

        return static::$memo[$key] ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        unset(static::$memo[$key]);
    }

    /**
     * Drop everything memoised. Long-running processes (queue workers, Octane)
     * and tests share one PHP process across many requests, so they need a way
     * to stop serving another request's values.
     */
    public static function flushMemo(): void
    {
        static::$memo = [];
    }

    public static function getGcashSettings(): array
    {
        return [
            'name' => static::getValue('gcash_name'),
            'number' => static::getValue('gcash_number'),
            'qr_url' => static::getValue('gcash_qr_url'),
        ];
    }

    public static function getBusinessProfile(): array
    {
        return [
            'business_name' => static::getValue('business_name', 'Ferosa Landscaping'),
            'business_address' => static::getValue('business_address', 'A. Arellano Ave. Mulawin, Orani, Bataan 2112'),
            'business_phone' => static::getValue('business_phone'),
            'business_email' => static::getValue('business_email'),
            'business_hours' => static::getValue('business_hours'),
            'service_area' => static::getValue('service_area', 'Orani, Bataan'),
            'booking_notice' => static::getValue('booking_notice', 'Appointments must be booked at least 24 hours in advance.'),
            'service_guarantee' => static::getValue('service_guarantee'),
            'cancellation_policy' => static::getValue('cancellation_policy'),
        ];
    }

    public static function setBusinessProfile(array $profile): void
    {
        foreach ($profile as $key => $value) {
            static::setValue($key, filled($value) ? trim((string) $value) : null);
        }
    }
}
