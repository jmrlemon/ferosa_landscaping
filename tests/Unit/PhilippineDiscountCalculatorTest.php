<?php

namespace Tests\Unit;

use App\Services\PhilippineDiscountCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhilippineDiscountCalculatorTest extends TestCase
{
    public function test_statutory_discount_removes_vat_before_applying_twenty_percent(): void
    {
        $result = (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            [['price' => '1120.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20]],
            vatRegistered: true,
            pricesIncludeVat: true,
        );

        $this->assertSame('1120.00', $result['gross_total']);
        $this->assertSame('120.00', $result['vat_removed']);
        $this->assertSame('1000.00', $result['discount_base']);
        $this->assertSame('200.00', $result['discount_amount']);
        $this->assertSame('800.00', $result['net_total']);
    }

    public function test_statutory_discount_leaves_ineligible_lines_unchanged(): void
    {
        $result = (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            [
                ['price' => '1120.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20],
                ['price' => '500.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE],
            ],
            vatRegistered: true,
            pricesIncludeVat: true,
        );

        $this->assertSame('1620.00', $result['gross_total']);
        $this->assertSame('1120.00', $result['eligible_gross']);
        $this->assertSame('1300.00', $result['net_total']);
    }

    public function test_bnpc_discount_applies_only_to_eligible_lines(): void
    {
        $result = (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_BNPC_5,
            [
                ['price' => '1000.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5],
                ['price' => '500.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE],
            ],
            bnpcWeeklyRemaining: '1300.00',
        );

        $this->assertSame('1000.00', $result['discount_base']);
        $this->assertSame('50.00', $result['discount_amount']);
        $this->assertSame('1450.00', $result['net_total']);
        $this->assertSame('0.00', $result['vat_removed']);
    }

    public function test_bnpc_discount_respects_the_remaining_weekly_purchase_cap(): void
    {
        $result = (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_BNPC_5,
            [['price' => '1000.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5]],
            bnpcWeeklyRemaining: '300.00',
        );

        $this->assertSame('300.00', $result['discount_base']);
        $this->assertSame('15.00', $result['discount_amount']);
        $this->assertSame('985.00', $result['net_total']);
    }

    public function test_bnpc_discount_uses_the_revised_2500_peso_weekly_purchase_cap_by_default(): void
    {
        $result = (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_BNPC_5,
            [['price' => '3000.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5]],
        );

        $this->assertSame('2500.00', $result['discount_base']);
        $this->assertSame('125.00', $result['discount_amount']);
        $this->assertSame('2875.00', $result['net_total']);
    }

    public function test_scheme_is_rejected_when_no_line_is_eligible(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PhilippineDiscountCalculator)->calculate(
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            [['price' => '1120.00', 'qty' => 1, 'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE]],
        );
    }
}
