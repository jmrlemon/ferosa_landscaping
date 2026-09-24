<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Pure, side-effect-free calculation rules for Philippine statutory discounts.
 *
 * All arithmetic is performed in centavos. The caller is responsible for
 * deciding whether a person and product are legally eligible; this class only
 * calculates explicitly tagged line items.
 */
class PhilippineDiscountCalculator
{
    public const SCHEME_NONE = 'none';

    public const SCHEME_STATUTORY_20 = 'statutory_20_vat_exempt';

    public const SCHEME_BNPC_5 = 'bnpc_5';

    /**
     * @param  list<array{price: float|int|string, qty?: int, discount_scheme?: string}>  $lines
     * @return array{
     *     scheme: string,
     *     gross_total: string,
     *     eligible_gross: string,
     *     vat_removed: string,
     *     discount_base: string,
     *     discount_rate: string,
     *     discount_amount: string,
     *     net_total: string
     * }
     */
    public function calculate(
        string $scheme,
        array $lines,
        bool $vatRegistered = false,
        bool $pricesIncludeVat = true,
        string $bnpcWeeklyRemaining = '2500.00',
        int $vatRatePercent = 12,
    ): array {
        if (! in_array($scheme, [self::SCHEME_STATUTORY_20, self::SCHEME_BNPC_5], true)) {
            throw new InvalidArgumentException('Unsupported discount scheme.');
        }

        $grossTotal = 0;
        $eligibleGross = 0;

        foreach ($lines as $line) {
            $quantity = (int) ($line['qty'] ?? 1);
            if ($quantity < 1) {
                throw new InvalidArgumentException('Line quantities must be at least one.');
            }

            $lineTotal = $this->toCentavos($line['price']) * $quantity;
            $grossTotal += $lineTotal;

            if (($line['discount_scheme'] ?? self::SCHEME_NONE) === $scheme) {
                $eligibleGross += $lineTotal;
            }
        }

        if ($eligibleGross <= 0) {
            throw new InvalidArgumentException('No order line is eligible for the selected discount.');
        }

        if ($scheme === self::SCHEME_STATUTORY_20) {
            $vatRemoved = $vatRegistered && $pricesIncludeVat
                ? $this->includedTax($eligibleGross, $vatRatePercent)
                : 0;
            $discountBase = $eligibleGross - $vatRemoved;
            $discountRateBasisPoints = 2000;
        } else {
            $vatRemoved = 0;
            $discountBase = min($eligibleGross, max(0, $this->toCentavos($bnpcWeeklyRemaining)));
            $discountRateBasisPoints = 500;
        }

        $discountAmount = $this->percentage($discountBase, $discountRateBasisPoints);
        $netTotal = max(0, $grossTotal - $vatRemoved - $discountAmount);

        return [
            'scheme' => $scheme,
            'gross_total' => $this->formatCentavos($grossTotal),
            'eligible_gross' => $this->formatCentavos($eligibleGross),
            'vat_removed' => $this->formatCentavos($vatRemoved),
            'discount_base' => $this->formatCentavos($discountBase),
            'discount_rate' => number_format($discountRateBasisPoints / 100, 2, '.', ''),
            'discount_amount' => $this->formatCentavos($discountAmount),
            'net_total' => $this->formatCentavos($netTotal),
        ];
    }

    private function includedTax(int $vatInclusiveCentavos, int $ratePercent): int
    {
        if ($ratePercent < 0 || $ratePercent > 100) {
            throw new InvalidArgumentException('VAT rate must be between zero and 100.');
        }

        $denominator = 100 + $ratePercent;

        return intdiv(($vatInclusiveCentavos * $ratePercent) + intdiv($denominator, 2), $denominator);
    }

    private function percentage(int $centavos, int $basisPoints): int
    {
        return intdiv(($centavos * $basisPoints) + 5000, 10000);
    }

    private function toCentavos(float|int|string $amount): int
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Money values must be numeric.');
        }

        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function formatCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
