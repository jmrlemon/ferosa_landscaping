<?php

namespace App\Services;

use InvalidArgumentException;

class MaterialCoverageCalculator
{
    /**
     * @return array{
     *     area_sqm:float,
     *     waste_percent:float,
     *     purchase_area_sqm:float,
     *     coverage_sqm_per_unit:float,
     *     recommended_quantity:int,
     *     selected_quantity:int,
     *     selected_coverage_sqm:float,
     *     coverage_shortfall_sqm:float,
     *     coverage_surplus_sqm:float,
     *     unit_price:float,
     *     material_cost:float,
     *     stock_qty:int,
     *     has_enough_stock:bool,
     *     stock_shortage:int
     * }
     */
    public static function calculate(
        int $areaSqm,
        float $coverageSqmPerUnit,
        float $wastePercent,
        float $unitPrice,
        int $stockQty,
        ?int $selectedQuantity = null,
    ): array {
        if ($areaSqm < 1) {
            throw new InvalidArgumentException('Area must be greater than zero.');
        }

        if (! is_finite($coverageSqmPerUnit) || $coverageSqmPerUnit <= 0) {
            throw new InvalidArgumentException('Coverage per unit must be greater than zero.');
        }

        if (! is_finite($wastePercent) || $wastePercent < 0 || $wastePercent > 100) {
            throw new InvalidArgumentException('Waste percentage must be between zero and one hundred.');
        }

        if (! is_finite($unitPrice) || $unitPrice < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative.');
        }

        if ($stockQty < 0) {
            throw new InvalidArgumentException('Stock quantity cannot be negative.');
        }

        if ($selectedQuantity !== null && $selectedQuantity < 1) {
            throw new InvalidArgumentException('Selected quantity must be greater than zero.');
        }

        $purchaseArea = round($areaSqm * (1 + ($wastePercent / 100)), 4);
        $recommendedQuantity = (int) ceil($purchaseArea / $coverageSqmPerUnit);
        $selectedQuantity ??= $recommendedQuantity;
        $selectedCoverage = round($selectedQuantity * $coverageSqmPerUnit, 4);

        return [
            'area_sqm' => (float) $areaSqm,
            'waste_percent' => round($wastePercent, 2),
            'purchase_area_sqm' => $purchaseArea,
            'coverage_sqm_per_unit' => round($coverageSqmPerUnit, 4),
            'recommended_quantity' => $recommendedQuantity,
            'selected_quantity' => $selectedQuantity,
            'selected_coverage_sqm' => $selectedCoverage,
            'coverage_shortfall_sqm' => round(max(0, $areaSqm - $selectedCoverage), 4),
            'coverage_surplus_sqm' => round(max(0, $selectedCoverage - $areaSqm), 4),
            'unit_price' => round($unitPrice, 2),
            'material_cost' => round($selectedQuantity * $unitPrice, 2),
            'stock_qty' => $stockQty,
            'has_enough_stock' => $stockQty >= $recommendedQuantity,
            'stock_shortage' => max(0, $recommendedQuantity - $stockQty),
        ];
    }
}
