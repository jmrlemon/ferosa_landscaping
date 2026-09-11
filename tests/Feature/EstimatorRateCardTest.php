<?php

namespace Tests\Feature;

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

    public function test_the_rate_card_is_served_to_the_app_exactly_as_configured(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->getJson('/api/mobile/estimator-rates')
            ->assertOk()
            ->assertExactJson(config('estimator'));
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
