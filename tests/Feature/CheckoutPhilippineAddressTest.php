<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutPhilippineAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_step_by_step_philippine_address_fields(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('name="delivery_province_code"', false)
            ->assertSee('name="delivery_city_code"', false)
            ->assertSee('name="delivery_barangay_code"', false)
            ->assertSee('House/Unit No. and street name', false)
            ->assertSeeText('Bataan')
            ->assertDontSeeText('Metro Manila');
    }

    public function test_checkout_explains_that_vat_inclusive_prices_are_not_charged_twice(): void
    {
        config([
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
        ]);

        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSeeText('Item prices already include VAT. Subtotal before VAT plus VAT equals your total.');
    }

    public function test_checkout_explains_final_prices_when_business_is_not_vat_registered(): void
    {
        config(['discounts.vat_registered' => false]);

        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSeeText('Displayed prices are final. No additional VAT is added at checkout.');
    }

    public function test_checkout_does_not_add_a_separate_vat_line_when_registration_is_unconfirmed(): void
    {
        config(['discounts.vat_registered' => null]);

        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSeeText('No separate VAT is added at checkout. Ferosa’s VAT registration status is still being confirmed.');
    }

    public function test_online_checkout_is_blocked_for_unsupported_vat_exclusive_pricing(): void
    {
        config([
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => false,
        ]);

        [$customer] = $this->customerWithCart();

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSeeText('Online checkout is paused because VAT-exclusive price calculation is not configured.');

        $this->actingAs($customer)
            ->post(route('checkout.store'), [])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_address_options_are_authenticated_and_scoped_to_their_parent(): void
    {
        $this->getJson(route('philippine-addresses.localities', '0300800000'))
            ->assertUnauthorized();

        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->getJson(route('philippine-addresses.localities', '0300800000'))
            ->assertOk()
            ->assertJsonFragment(['code' => '0300809000', 'name' => 'Orani']);

        $barangays = $this->actingAs($customer)
            ->getJson(route('philippine-addresses.barangays', '0300809000'))
            ->assertOk()
            ->assertJsonFragment(['code' => '0300809010', 'name' => 'Mulawin']);

        $this->assertNotContains(
            '0301101003',
            collect($barangays->json('data'))->pluck('code')->all()
        );

        $this->actingAs($customer)
            ->getJson(route('philippine-addresses.localities', '1300000000'))
            ->assertNotFound();

        $this->actingAs($customer)
            ->getJson(route('philippine-addresses.barangays', '1381300000'))
            ->assertNotFound();
    }

    public function test_delivery_checkout_rejects_mismatched_philippine_address_codes(): void
    {
        [$customer] = $this->customerWithCart();

        $this->actingAs($customer)
            ->post(route('checkout.store'), $this->deliveryPayload([
                'delivery_province_code' => '0301400000',
            ]))
            ->assertSessionHasErrors('delivery_province_code');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_checkout_stores_the_canonical_readable_address(): void
    {
        Mail::fake();
        Notification::fake();
        [$customer] = $this->customerWithCart();

        $this->actingAs($customer)
            ->post(route('checkout.store'), $this->deliveryPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame('123 Mabini Street, Mulawin', $order->delivery_address);
        $this->assertSame('Orani, Bataan', $order->delivery_city);
    }

    /** @return array{User, Product} */
    private function customerWithCart(): array
    {
        $customer = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'Address Test Plant',
            'price' => 450,
            'stock_qty' => 5,
            'category' => 'plants',
            'is_active' => true,
        ]);

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        return [$customer, $product];
    }

    /** @param array<string, string> $overrides */
    private function deliveryPayload(array $overrides = []): array
    {
        return array_merge([
            'delivery_method' => 'delivery',
            'delivery_name' => 'Juan Dela Cruz',
            'delivery_phone' => '09171234567',
            'delivery_address' => '123 Mabini Street',
            'delivery_province_code' => '0300800000',
            'delivery_city_code' => '0300809000',
            'delivery_barangay_code' => '0300809010',
            'payment_method' => 'cod',
        ], $overrides);
    }
}
