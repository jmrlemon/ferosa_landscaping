<?php

namespace Tests\Unit;

use App\Services\MaterialCoverageCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaterialCoverageCalculatorTest extends TestCase
{
    public function test_it_recommends_whole_sale_units_with_a_waste_allowance(): void
    {
        $result = MaterialCoverageCalculator::calculate(
            areaSqm: 100,
            coverageSqmPerUnit: 0.5,
            wastePercent: 10,
            unitPrice: 75,
            stockQty: 250,
        );

        $this->assertSame(220, $result['recommended_quantity']);
        $this->assertSame(220, $result['selected_quantity']);
        $this->assertSame(110.0, $result['purchase_area_sqm']);
        $this->assertSame(110.0, $result['selected_coverage_sqm']);
        $this->assertSame(16500.0, $result['material_cost']);
        $this->assertTrue($result['has_enough_stock']);
        $this->assertSame(0, $result['stock_shortage']);
    }

    public function test_it_rounds_up_when_the_area_is_not_an_exact_multiple(): void
    {
        $result = MaterialCoverageCalculator::calculate(
            areaSqm: 100,
            coverageSqmPerUnit: 3,
            wastePercent: 10,
            unitPrice: 500,
            stockQty: 100,
        );

        $this->assertSame(37, $result['recommended_quantity']);
        $this->assertSame(111.0, $result['selected_coverage_sqm']);
    }

    public function test_it_reports_stock_shortage_without_reducing_the_recommendation(): void
    {
        $result = MaterialCoverageCalculator::calculate(
            areaSqm: 100,
            coverageSqmPerUnit: 0.5,
            wastePercent: 10,
            unitPrice: 75,
            stockQty: 180,
        );

        $this->assertSame(220, $result['recommended_quantity']);
        $this->assertFalse($result['has_enough_stock']);
        $this->assertSame(40, $result['stock_shortage']);
    }

    public function test_it_reports_coverage_when_the_customer_overrides_the_quantity(): void
    {
        $result = MaterialCoverageCalculator::calculate(
            areaSqm: 100,
            coverageSqmPerUnit: 0.5,
            wastePercent: 10,
            unitPrice: 75,
            stockQty: 250,
            selectedQuantity: 190,
        );

        $this->assertSame(220, $result['recommended_quantity']);
        $this->assertSame(190, $result['selected_quantity']);
        $this->assertSame(95.0, $result['selected_coverage_sqm']);
        $this->assertSame(5.0, $result['coverage_shortfall_sqm']);
        $this->assertSame(0.0, $result['coverage_surplus_sqm']);
        $this->assertSame(14250.0, $result['material_cost']);
    }

    #[DataProvider('invalidInputProvider')]
    public function test_it_rejects_invalid_inputs(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        MaterialCoverageCalculator::calculate(...$arguments);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'zero area' => [[0, 1, 10, 100, 5]],
            'zero unit coverage' => [[100, 0, 10, 100, 5]],
            'negative waste' => [[100, 1, -1, 100, 5]],
            'waste above one hundred percent' => [[100, 1, 101, 100, 5]],
            'negative price' => [[100, 1, 10, -1, 5]],
            'negative stock' => [[100, 1, 10, 100, -1]],
            'zero selected quantity' => [[100, 1, 10, 100, 5, 0]],
        ];
    }
}
