<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Appointment scope adjustments were removed. Existing scope notes remain
 * readable as historical data, but no user can create or change them.
 */
class AppointmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAppointment(User $customer, ServiceType $service, string $status = 'scheduled'): Appointment
    {
        $at = Carbon::now()->addDays(3)->setTime(10, 30);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $at,
            'slot_key' => Appointment::slotKey($service->id, $at),
            'appointment_amount' => 3000,
            'status' => $status,
            'notes' => 'Also need lawn care on the same visit.',
        ]);
    }

    private function seedActors(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'Hardscaping',
            'default_fee' => 3000,
            'is_active' => true,
        ]);

        return [$admin, $customer, $service];
    }

    public function test_scope_and_cost_adjustment_is_not_available_to_admins(): void
    {
        [$admin, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service);

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertDontSeeText('Adjust Scope & Cost')
            ->assertDontSeeText('Save Scope');

        $this->actingAs($admin)
            ->put('/admin/appointments/'.$appointment->id.'/scope', [
                'appointment_amount' => '3800.00',
                'scope_notes' => 'Hardscaping (front walkway) + Lawn Care (front and side lawn)',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'appointment_amount' => 3000,
            'scope_notes' => null,
        ]);
    }

    public function test_the_customer_sees_the_confirmed_scope(): void
    {
        [, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service);
        $appointment->update(['scope_notes' => 'Hardscaping + Lawn Care']);

        $this->actingAs($customer)
            ->get(route('appointments'))
            ->assertOk()
            ->assertSee('Hardscaping + Lawn Care');
    }

    public function test_booking_page_does_not_promise_scope_or_cost_adjustments(): void
    {
        [, $customer] = $this->seedActors();
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
            ->assertSee('id="booking-form"', false)
            ->assertDontSeeText('Need more than one service on this visit?')
            ->assertDontSee('Also need lawn care on the same visit.', false)
            ->assertDontSeeText('Final scope and cost may be confirmed after Ferosa reviews your space and requirements.');
    }
}
