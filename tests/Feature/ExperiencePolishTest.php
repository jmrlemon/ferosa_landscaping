<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Project;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperiencePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_requires_customer_to_choose_a_time_explicitly(): void
    {
        $customer = User::factory()->create();

        $service = ServiceType::query()->create([
            'name' => 'Garden Design Consultation',
            'slug' => 'garden-design',
            'description' => 'A design consultation.',
            'duration_minutes' => 60,
            'default_fee' => 500,
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->withSession($this->estimatorBookingSession($service))
            ->get(route('schedule'))
            ->assertOk()
            ->assertSee('function clearTimeSelection()', false)
            ->assertSee("alert('Please select a time.')", false)
            ->assertDontSee("pickable[0].classList.add('selected')", false)
            ->assertDontSee("first.classList.add('selected')", false);
    }

    public function test_public_work_link_only_appears_when_published_work_exists(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('Our work');

        Project::query()->create([
            'title' => 'Courtyard Refresh',
            'slug' => 'courtyard-refresh',
            'summary' => 'A completed residential courtyard project.',
            'is_published' => true,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Our work');
    }

    public function test_public_contact_has_an_online_fallback_when_contact_settings_are_empty(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Message the team')
            ->assertSee(route('login'));
    }

    public function test_customer_pages_have_descriptive_browser_titles(): void
    {
        $customer = User::factory()->create();

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('<title>Shop - Ferosa Landscaping</title>', false);

        $this->actingAs($customer)
            ->get(route('estimator'))
            ->assertOk()
            ->assertSee('<title>Cost Estimator - Ferosa Landscaping</title>', false);
    }

    public function test_past_open_appointments_are_flagged_for_a_team_update(): void
    {
        $customer = User::factory()->create();
        $service = ServiceType::query()->create([
            'name' => 'Site Visit',
            'default_fee' => 500,
            'is_active' => true,
        ]);

        Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => now()->subDay(),
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
            'appointment_amount' => 500,
        ]);

        $this->actingAs($customer)
            ->get(route('appointments'))
            ->assertOk()
            ->assertSee('Past visit - awaiting team update');
    }

    public function test_zero_value_appointments_do_not_display_as_unpaid_zero_peso_records(): void
    {
        $customer = User::factory()->create();
        $service = ServiceType::query()->create([
            'name' => 'Initial Consultation',
            'default_fee' => 0,
            'is_active' => true,
        ]);

        Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => now()->subDay(),
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'appointment_amount' => 0,
        ]);

        $this->actingAs($customer)
            ->get(route('appointments'))
            ->assertOk()
            ->assertSee('No payment due')
            ->assertSee('No charge')
            ->assertDontSee('PHP 0.00');
    }

    public function test_old_open_orders_prompt_the_customer_to_request_an_update(): void
    {
        $customer = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-OLD-001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 1200,
            'items' => [],
        ]);
        $order->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->assertTrue($order->fresh()->needsStatusFollowUp());

        $this->actingAs($customer)
            ->get(route('orders'))
            ->assertOk()
            ->assertSee('Needs team update')
            ->assertSee('Message the team');
    }

    public function test_hardscaping_estimates_use_hardscaping_package_copy(): void
    {
        $hardscaping = config('estimator.project_types.hardscaping.packages');

        $this->assertSame('Essential Hardscape', $hardscaping['standard']['package_title']);
        $this->assertContains('Base preparation and drainage allowance', $hardscaping['standard']['examples']);
        $this->assertSame('Signature Stonework', $hardscaping['luxury']['package_title']);

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('estimator'))
            ->assertOk()
            ->assertSee('PROJECT_PACKAGES', false)
            ->assertSee('Essential Hardscape');
    }
}
