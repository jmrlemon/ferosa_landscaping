<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\AppointmentScopeUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A customer who wants two services on one date and time is asking for one
 * crew visit with two jobs in it, not two appointments. The booking stays a
 * single row; an administrator widens its scope and total afterwards.
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

    public function test_an_admin_can_widen_the_scope_and_total_of_a_visit(): void
    {
        Notification::fake();
        [$admin, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service);

        $this->actingAs($admin)
            ->put(route('admin.appointments.scope', $appointment), [
                'appointment_amount' => '3800.00',
                'scope_notes' => 'Hardscaping (front walkway) + Lawn Care (front and side lawn)',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.appointments.show', $appointment));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'appointment_amount' => 3800,
            'scope_notes' => 'Hardscaping (front walkway) + Lawn Care (front and side lawn)',
        ]);

        // Still one visit occupying one slot.
        $this->assertSame(1, Appointment::query()->where('user_id', $customer->id)->count());

        Notification::assertSentTo($customer, AppointmentScopeUpdated::class);
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

    public function test_scope_cannot_be_quoted_below_what_was_already_paid(): void
    {
        [$admin, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service);

        Payment::query()->create([
            'payable_type' => Appointment::class,
            'payable_id' => $appointment->id,
            'amount' => 3000,
            'method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.appointments.scope', $appointment), [
                'appointment_amount' => '500.00',
            ])
            ->assertSessionHasErrors('appointment_amount');

        $this->assertSame(3000.0, (float) $appointment->refresh()->appointment_amount);
    }

    public function test_scope_is_locked_once_the_visit_is_completed(): void
    {
        [$admin, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service, 'completed');

        $this->actingAs($admin)
            ->put(route('admin.appointments.scope', $appointment), [
                'appointment_amount' => '9000.00',
            ])
            ->assertSessionHasErrors('appointment_amount');

        $this->assertSame(3000.0, (float) $appointment->refresh()->appointment_amount);
    }

    public function test_customers_cannot_adjust_their_own_scope(): void
    {
        [, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service);

        $this->actingAs($customer)
            ->put(route('admin.appointments.scope', $appointment), [
                'appointment_amount' => '0.00',
            ])
            ->assertForbidden();
    }
}
