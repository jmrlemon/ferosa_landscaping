<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the estimator's single source of truth.
 *
 * The rates used to be hardcoded twice — once in the Blade view's JS and once
 * in the Android app's NativeEstimatorScreen.kt — and the two copies had
 * already drifted. Both surfaces now read config/estimator.php, and these tests
 * fail if either one starts hardcoding again.
 */
class EstimatorRateCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_rate_card(): void
    {
        $this->getJson('/api/mobile/estimator-rates')->assertUnauthorized();
    }

    public function test_the_endpoint_serves_the_configured_rate_card(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($customer)
            ->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertJsonStructure([
                'project_types',
                'tiers',
                'addons',
                'quick_sizes',
                'estimate_products',
                'range' => ['low', 'high'],
                'defaults' => ['project_type', 'tier', 'size'],
            ]);

        $response->assertJsonPath(
            'project_types.hardscaping.rate',
            config('estimator.project_types.hardscaping.rate')
        );
        $response->assertJsonPath(
            'tiers.luxury.multiplier',
            config('estimator.tiers.luxury.multiplier')
        );
        $response->assertJsonPath(
            'addons.pergola.amount',
            config('estimator.addons.pergola.amount')
        );
    }

    public function test_the_rate_card_is_served_as_configured_with_an_empty_product_catalog(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertExactJson(array_merge(config('estimator'), ['estimate_products' => []]));
    }

    public function test_area_coverage_metadata_is_additive_in_the_mobile_rate_card(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $grass = Product::query()->create([
            'name' => 'Carabao Grass',
            'price' => 250,
            'stock_qty' => 100,
            'category' => 'grass',
            'is_active' => true,
            'sale_unit' => 'sq m',
            'coverage_sqm_per_unit' => 1,
            'coverage_waste_percent' => 10,
        ]);

        $this->actingAs($customer)
            ->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertJsonPath('estimate_products.0.id', $grass->id)
            ->assertJsonPath('estimate_products.0.sale_unit', 'sq m')
            ->assertJsonPath('estimate_products.0.coverage_sqm_per_unit', 1)
            ->assertJsonPath('estimate_products.0.coverage_waste_percent', 10);
    }

    public function test_the_web_estimator_renders_an_area_based_material_recommendation(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        Product::query()->create([
            'name' => 'Carabao Grass',
            'price' => 250,
            'stock_qty' => 150,
            'category' => 'grass',
            'is_active' => true,
            'sale_unit' => 'sq m',
            'coverage_sqm_per_unit' => 1,
            'coverage_waste_percent' => 10,
        ]);

        $html = $this->actingAs($customer)->get('/estimator')->assertOk()->getContent();

        $this->assertStringContainsString('id="coverage-area-input"', $html);
        $this->assertStringContainsString('id="coverage-area-error"', $html);
        $this->assertStringContainsString('id="material-recommendation"', $html);
        $this->assertStringContainsString('data-sale-unit="sq m"', $html);
        $this->assertStringContainsString('data-coverage-sqm="1"', $html);
        $this->assertStringContainsString('data-waste-percent="10"', $html);
        $this->assertStringContainsString('Recommended order', $html);
        $this->assertStringContainsString('function applyAreaRecommendation', $html);
        $this->assertStringContainsString('function updateCoverageAreaUI', $html);
    }

    public function test_non_grass_and_non_stone_products_never_expose_area_coverage(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        Product::query()->create([
            'name' => 'Monstera Deliciosa',
            'price' => 1200,
            'stock_qty' => 100,
            'category' => 'Plants',
            'is_active' => true,
            // Simulates legacy or directly imported data that bypassed admin validation.
            'sale_unit' => 'plant',
            'coverage_sqm_per_unit' => 1,
            'coverage_waste_percent' => 10,
        ]);

        $html = $this->actingAs($customer)->get('/estimator')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="coverage-area-input"', $html);
        $this->assertStringNotContainsString('data-coverage-sqm="1"', $html);

        $this->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertJsonPath('estimate_products.0.sale_unit', null)
            ->assertJsonPath('estimate_products.0.coverage_sqm_per_unit', null)
            ->assertJsonPath('estimate_products.0.coverage_waste_percent', null);
    }

    public function test_the_web_estimator_renders_rates_from_the_config(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        // A rate the page must show, taken from config rather than repeated here
        // — if someone reverts the view to a literal, this stops matching.
        config()->set('estimator.project_types.hardscaping.rate', 137);
        config()->set('estimator.addons.pergola.amount', 81234);

        $html = $this->actingAs($customer)->get('/estimator')->assertOk()->getContent();

        $this->assertStringContainsString('₱137/sq m', $html);
        $this->assertStringContainsString('+ ₱81,234', $html);
    }

    public function test_each_quality_tier_has_its_own_web_visual(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $expectedVisuals = [
            'standard' => 'images/quality-tier-standard.png',
            'premium' => 'images/quality-tier-premium.png',
            'luxury' => 'images/quality-tier-luxury.png',
        ];

        $html = $this->actingAs($customer)->get('/estimator')->assertOk()->getContent();

        foreach ($expectedVisuals as $path) {
            $this->assertFileExists(public_path($path));
            $this->assertStringContainsString(json_encode(asset($path), JSON_THROW_ON_ERROR), $html);
        }

        $this->assertStringContainsString('visualImage.src = visual.src;', $html);
        $this->assertStringContainsString('zoomImage.src = visual.src;', $html);
        $this->assertStringNotContainsString('tier-package-visuals.png', $html);
    }

    public function test_every_quick_size_the_config_lists_is_offered_on_the_web_page(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $html = $this->actingAs($customer)->get('/estimator')->assertOk()->getContent();

        foreach (config('estimator.quick_sizes') as $size) {
            $this->assertStringContainsString(
                'setSize('.$size.')',
                $html,
                "The estimator page is missing the {$size} sq m quick pick."
            );
        }
    }
}
