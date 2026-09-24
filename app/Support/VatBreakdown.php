<?php

namespace App\Support;

/**
 * Describes a VAT-inclusive amount for customer-facing financial documents.
 *
 * The numeric breakdown is shown only when the business tax profile and the
 * aggregate catalogue VAT assumption are both explicitly confirmed.
 */
final class VatBreakdown
{
    /**
     * @return array{show: bool, rate_percent: int, total: float, before_vat: float, vat_amount: float}
     */
    public static function forAmount(float|int|string $amount): array
    {
        return self::calculate($amount, (bool) config('discounts.all_checkout_products_vatable', false));
    }

    /**
     * @return array{show: bool, rate_percent: int, total: float, before_vat: float, vat_amount: float}
     */
    public static function forServiceAmount(float|int|string $amount): array
    {
        return self::calculate($amount, (bool) config('discounts.all_services_vatable', false));
    }

    /**
     * @return array{show: bool, rate_percent: int, total: float, before_vat: float, vat_amount: float}
     */
    private static function calculate(float|int|string $amount, bool $allItemsVatable): array
    {
        $total = round(max(0, (float) $amount), 2);
        $ratePercent = max(0, (int) config('discounts.vat_rate_percent', 12));
        $show = config('discounts.vat_registered') === true
            && (bool) config('discounts.prices_include_vat', true)
            && $allItemsVatable
            && $ratePercent > 0
            && $total > 0;

        if (! $show) {
            return [
                'show' => false,
                'rate_percent' => $ratePercent,
                'total' => $total,
                'before_vat' => $total,
                'vat_amount' => 0.0,
            ];
        }

        $vatAmount = round($total * $ratePercent / (100 + $ratePercent), 2);

        return [
            'show' => true,
            'rate_percent' => $ratePercent,
            'total' => $total,
            'before_vat' => round($total - $vatAmount, 2),
            'vat_amount' => $vatAmount,
        ];
    }

    private function __construct() {}
}
