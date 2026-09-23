<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_books_a_validated_location_that_admin_and_staff_can_see(): void
    {
        Carbon::setTestNow('2026-09-23 09:00:00');
        Mail::fake();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        ServiceType::query()->create([
            'name' => 'Garden Design Consultation',
            'default_fee' => 1500,
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->post(route('estimator.prepare'), [
                'project_type' => 'design',
                'size' => 100,
                'tier' => 'standard',
                'addons' => [],
                'products' => [],
            ])
            ->assertRedirect(route('schedule'));

        $this->actingAs($customer)
            ->get(route('schedule'))
            ->assertOk()
            ->assertSee('name="site_province_code"', false)
            ->assertSee('name="site_city_code"', false)
            ->assertSee('name="site_barangay_code"', false)
            ->assertSee('name="site_street"', false)
            ->assertSeeText('Bataan')
            ->assertDontSeeText('Metro Manila');

        $this->actingAs($customer)
            ->post(route('schedule.store'), $this->bookingPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointments'));

        $appointment = Appointment::query()->sole();
        $this->assertSame(
            '123 Mabini Street, Mulawin, Orani, Bataan, Philippines',
            $appointment->site_address
        );

        foreach ([$admin, $staff] as $teamMember) {
            $this->actingAs($teamMember)
                ->get(route('admin.appointments.show', $appointment))
                ->assertOk()
                ->assertSeeText('Visit Location')
                ->assertSeeText($appointment->site_address);
        }
    }

    public function test_booking_rejects_a_city_outside_the_selected_province(): void
    {
        Carbon::setTestNow('2026-09-23 09:00:00');
        $customer = User::factory()->create(['role' => 'user']);
        ServiceType::query()->create([
            'name' => 'Garden Design Consultation',
            'default_fee' => 1500,
            'is_active' => true,
        ]);

        $this->actingAs($customer)->post(route('estimator.prepare'), [
            'project_type' => 'design',
            'size' => 100,
            'tier' => 'standard',
            'addons' => [],
            'products' => [],
        ]);

        $this->actingAs($customer)
            ->post(route('schedule.store'), $this->bookingPayload([
                'site_province_code' => '0301400000',
            ]))
            ->assertSessionHasErrors('site_province_code');

        $this->assertDatabaseCount('appointments', 0);
    }

    /** @param array<string, string> $overrides */
    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_at' => '2026-09-26 09:00:00',
            'site_province_code' => '0300800000',
            'site_city_code' => '0300809000',
            'site_barangay_code' => '0300809010',
            'site_street' => '123 Mabini Street',
            'notes' => 'Please inspect the front garden.',
        ], $overrides);
    }
}
