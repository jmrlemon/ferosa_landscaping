<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutVatSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_inclusive_prices_show_the_vat_portion_without_adding_it_to_the_total(): void
    {
        config([
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
            'discounts.all_checkout_products_vatable' => true,
        ]);

        $customer = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'VAT Summary Plant',
            'price' => 600,
            'stock_qty' => 5,
            'category' => 'plants',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $response = $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Subtotal before VAT', false)
            ->assertSeeText('VAT (12%)')
            ->assertSee('&#8369;64.29', false)
            ->assertSee('id="summary-vat-row"', false);

        $this->assertSame(
            '&#8369;535.71',
            preg_match('/<span id="summary-subtotal"[^>]*>(.*?)<\/span>/s', $response->getContent(), $subtotalMatch)
                ? $subtotalMatch[1]
                : null,
        );
        $this->assertSame(
            '&#8369;600',
            preg_match('/<span id="summary-total"[^>]*>(.*?)<\/span>/s', $response->getContent(), $match)
                ? $match[1]
                : null,
        );
    }

    public function test_checkout_hides_numeric_vat_when_registration_is_unconfirmed_or_not_registered(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        foreach ([
            ['discounts.vat_registered' => null, 'discounts.prices_include_vat' => true, 'discounts.all_checkout_products_vatable' => true],
            ['discounts.vat_registered' => false, 'discounts.prices_include_vat' => true, 'discounts.all_checkout_products_vatable' => true],
            ['discounts.vat_registered' => true, 'discounts.prices_include_vat' => false, 'discounts.all_checkout_products_vatable' => true],
            ['discounts.vat_registered' => true, 'discounts.prices_include_vat' => true, 'discounts.all_checkout_products_vatable' => false],
        ] as $taxProfile) {
            config($taxProfile);

            $this->actingAs($customer)
                ->get(route('checkout'))
                ->assertOk()
                ->assertDontSee('id="summary-vat-row"', false)
                ->assertDontSeeText('VAT (12%)');
        }
    }
}
