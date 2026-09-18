<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePricePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_value_services_are_presented_as_assessment_quotes_not_zero_cost_work(): void
    {
        $service = ServiceType::query()->create([
            'name' => 'Hardscaping Quote',
            'default_fee' => 0,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Quote after site assessment')
            ->assertDontSee('PHP 0');

        $this->actingAs(User::factory()->create())
            ->withSession($this->estimatorBookingSession($service, [
                'project_type' => 'hardscaping',
                'project_type_label' => 'Hardscaping',
            ]))
            ->get(route('schedule'))
            ->assertOk()
            ->assertSee('Quote after site assessment')
            ->assertDontSee('from PHP 0');
    }

    public function test_positive_service_fees_keep_their_starting_price_label(): void
    {
        ServiceType::query()->create([
            'name' => 'Garden Maintenance',
            'default_fee' => 2500,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('From PHP 2,500');
    }
}
