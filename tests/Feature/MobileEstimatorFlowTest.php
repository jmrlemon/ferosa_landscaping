<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileEstimatorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_prepare_an_estimate_for_mobile_booking(): void
    {
        $this->postJson(route('estimator.prepare'), [
            'project_type' => 'design',
            'size' => 50,
            'tier' => 'standard',
            'addons' => [],
            'products' => [],
        ])->assertUnauthorized();
    }

    public function test_json_estimate_preparation_stores_a_server_calculated_draft(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $serviceName = config('estimator.project_types.design.booking_service_name');
        $service = ServiceType::query()->create([
            'name' => $serviceName,
            'default_fee' => 1500,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 5,
            'category' => 'materials',
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->postJson(route('estimator.prepare'), [
            'project_type' => 'design',
            'size' => 10,
            'tier' => 'standard',
            'addons' => ['lighting'],
            'products' => [['id' => $product->id, 'qty' => 2]],
            // These forged values must be ignored. The server owns the quote.
            'service_type_id' => 999999,
            'total' => 1,
        ])->assertOk()->assertExactJson(['success' => true]);

        $expectedTotal = (float) config('estimator.project_types.design.rate') * 10
            * (float) config('estimator.tiers.standard.multiplier')
            + (float) config('estimator.addons.lighting.amount')
            + 500;

        $response->assertSessionHas('estimator_booking', function (array $draft) use ($expectedTotal, $product, $service): bool {
            return $draft['service_type_id'] === $service->id
                && $draft['snapshot']['products'][0]['id'] === $product->id
                && $draft['snapshot']['products'][0]['quantity'] === 2
                && abs((float) $draft['snapshot']['total'] - $expectedTotal) < 0.001;
        });
    }

    public function test_mobile_estimator_rate_card_includes_only_active_in_stock_products(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $available = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 5,
            'category' => 'materials',
            'is_active' => true,
        ]);
        Product::query()->create([
            'name' => 'Out of Stock Plant',
            'price' => 300,
            'stock_qty' => 0,
            'category' => 'plants',
            'is_active' => true,
        ]);
        Product::query()->create([
            'name' => 'Inactive Product',
            'price' => 350,
            'stock_qty' => 5,
            'category' => 'plants',
            'is_active' => false,
        ]);

        $this->actingAs($customer)
            ->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertJsonCount(1, 'estimate_products')
            ->assertJsonPath('estimate_products.0.id', $available->id)
            ->assertJsonPath('estimate_products.0.name', 'Garden Soil')
            ->assertJsonPath('estimate_products.0.stock_qty', 5);
    }
}
