<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\AppointmentMovedByTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Customers may move their own visit until it is CHANGE_NOTICE_HOURS away, and
 * are then told to message the team. This is the control that lets the team
 * honour that, so most of what matters here is that it is *not* bound by the
 * same window the customer is.
 */
class StaffRescheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: ServiceType} */
    private function seedActors(): array
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '+639171234567',
        ]);
        $service = ServiceType::query()->create([
            'name' => 'Lawn Care',
            'default_fee' => 1200,
            'is_active' => true,
        ]);

        return [$staff, $customer, $service];
    }

    private function makeAppointment(User $customer, ServiceType $service, Carbon $at, string $status = 'confirmed'): Appointment
    {
        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $at,
            'slot_key' => Appointment::slotKey($service->id, $at),
            'appointment_amount' => 1200,
            'status' => $status,
        ]);
    }

    public function test_staff_can_move_a_visit_the_customer_can_no_longer_touch(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();

        // Inside the customer's notice window: they are being told to message
        // the team about exactly this visit.
        // A real dispatch slot later the same day: close enough that the
        // customer can no longer touch it. Do not reach for addHours() and then
        // setTime() - the second call throws the first one away.
        $bookedAt = Carbon::now()->setTime(16, 0);
        $appointment = $this->makeAppointment($customer, $service, $bookedAt);
        $this->assertFalse($appointment->isCustomerChangeable());

        $movedTo = Carbon::now()->addDays(3)->setTime(13, 0)->seconds(0);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $movedTo->format('Y-m-d'),
                'move_time' => '13:00',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'status',
                'Visit moved to '.$movedTo->format('M d, Y \a\t g:i A')
                    .'. The customer was notified in-app and an SMS was queued.'
            );

        $appointment->refresh();
        $this->assertTrue($movedTo->equalTo($appointment->appointment_at));
        $this->assertSame(Appointment::slotKey($service->id, $movedTo), $appointment->slot_key);

        // Unlike a customer move, the team's own change does not send a
        // confirmed visit back for re-confirmation.
        $this->assertSame('confirmed', $appointment->status);

        Notification::assertSentTo($customer, AppointmentMovedByTeam::class);
        Queue::assertPushed(
            SendSmsJob::class,
            fn (SendSmsJob $job): bool => $job->recipient() === $customer->phone_number
                && $job->message() === 'Ferosa: Your Lawn Care appointment was rescheduled from '
                    .$bookedAt->format('M d, Y g:i A').' to '
                    .$movedTo->format('M d, Y g:i A')
                    .'. Contact us if this new schedule does not work for you.'
        );
        Queue::assertPushed(SendSmsJob::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.reschedule.staff']);
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.reschedule.staff')->count());
    }

    public function test_staff_move_without_a_verified_customer_phone_keeps_the_in_app_notification(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $customer->update(['phone_verified_at' => null]);

        $appointment = $this->makeAppointment(
            $customer,
            $service,
            Carbon::now()->addDays(2)->setTime(9, 0)
        );
        $movedTo = Carbon::now()->addDays(4)->setTime(13, 0)->seconds(0);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $movedTo->format('Y-m-d'),
                'move_time' => '13:00',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment))
            ->assertSessionHas(
                'status',
                'Visit moved to '.$movedTo->format('M d, Y \a\t g:i A')
                    .'. The customer was notified in-app, but no SMS was queued because there is no verified phone number.'
            );

        $this->assertTrue($movedTo->equalTo($appointment->refresh()->appointment_at));
        Notification::assertSentTo($customer, AppointmentMovedByTeam::class);
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_staff_move_without_a_customer_phone_keeps_the_in_app_notification(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $customer->update([
            'phone_number' => null,
            'phone_verified_at' => null,
        ]);

        $appointment = $this->makeAppointment(
            $customer,
            $service,
            Carbon::now()->addDays(2)->setTime(9, 0)
        );
        $movedTo = Carbon::now()->addDays(4)->setTime(13, 0)->seconds(0);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $movedTo->format('Y-m-d'),
                'move_time' => '13:00',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment));

        $this->assertTrue($movedTo->equalTo($appointment->refresh()->appointment_at));
        Notification::assertSentTo($customer, AppointmentMovedByTeam::class);
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_the_move_control_renders_on_an_upcoming_visit_and_not_on_a_finished_one(): void
    {
        [$staff, $customer, $service] = $this->seedActors();

        $upcoming = $this->makeAppointment($customer, $service, Carbon::now()->addDays(2)->setTime(10, 30));
        $this->actingAs($staff)
            ->get(route('admin.appointments.show', $upcoming))
            ->assertOk()
            ->assertSee('Move Visit')
            ->assertSee(route('admin.appointments.reschedule', $upcoming));

        $completed = $this->makeAppointment($customer, $service, Carbon::now()->subDays(3)->setTime(9, 0), 'completed');
        $this->actingAs($staff)
            ->get(route('admin.appointments.show', $completed))
            ->assertOk()
            ->assertDontSee(route('admin.appointments.reschedule', $completed));
    }

    public function test_staff_cannot_move_a_visit_off_a_dispatch_slot_or_into_the_past(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $appointment = $this->makeAppointment($customer, $service, Carbon::now()->addDays(2)->setTime(9, 0));
        $original = $appointment->appointment_at->copy();

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'move_time' => '03:17',
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => Carbon::now()->subDay()->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->assertTrue($original->equalTo($appointment->refresh()->appointment_at));
        Notification::assertNothingSent();
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_staff_cannot_move_a_visit_to_its_existing_time(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $bookedAt = Carbon::now()->addDays(2)->setTime(9, 0)->seconds(0);
        $appointment = $this->makeAppointment($customer, $service, $bookedAt);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $bookedAt->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->assertTrue($bookedAt->equalTo($appointment->refresh()->appointment_at));
        Notification::assertNothingSent();
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_staff_cannot_move_a_visit_onto_another_booking_for_the_same_service(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $other = User::factory()->create(['role' => 'user']);

        $appointment = $this->makeAppointment($customer, $service, Carbon::now()->addDays(2)->setTime(9, 0));
        $taken = Carbon::now()->addDays(4)->setTime(14, 30)->seconds(0);
        $this->makeAppointment($other, $service, $taken);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $taken->format('Y-m-d'),
                'move_time' => '14:30',
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->assertSame(2, Appointment::query()->count());
        Notification::assertNothingSent();
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_a_completed_visit_cannot_be_moved_and_customers_cannot_reach_the_control(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();

        $completed = $this->makeAppointment($customer, $service, Carbon::now()->subDays(2)->setTime(9, 0), 'completed');
        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $completed), [
                'move_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertSessionHasErrors('appointment_at');

        $upcoming = $this->makeAppointment($customer, $service, Carbon::now()->addDays(2)->setTime(10, 30));
        $this->actingAs($customer)
            ->put(route('admin.appointments.reschedule', $upcoming), [
                'move_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertForbidden();

        Notification::assertNothingSent();
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_staff_cannot_move_an_archived_visit(): void
    {
        Notification::fake();
        Queue::fake();
        [$staff, $customer, $service] = $this->seedActors();
        $bookedAt = Carbon::now()->addDays(2)->setTime(9, 0)->seconds(0);
        $appointment = $this->makeAppointment($customer, $service, $bookedAt);
        $appointment->update(['archived_at' => now()]);

        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                'move_time' => '13:00',
            ])
            ->assertNotFound();

        $this->assertTrue($bookedAt->equalTo($appointment->refresh()->appointment_at));
        Notification::assertNothingSent();
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_sms_message_uses_a_safe_service_fallback(): void
    {
        [, $customer, $service] = $this->seedActors();
        $previousAt = Carbon::now()->addDays(2)->setTime(9, 0)->seconds(0);
        $movedTo = Carbon::now()->addDays(4)->setTime(13, 0)->seconds(0);
        $appointment = $this->makeAppointment($customer, $service, $movedTo);
        $appointment->setRelation('serviceType', null);

        $message = (new AppointmentMovedByTeam($appointment, $previousAt))->toSmsMessage();

        $this->assertSame(
            'Ferosa: Your Service appointment was rescheduled from '
                .$previousAt->format('M d, Y g:i A').' to '
                .$movedTo->format('M d, Y g:i A')
                .'. Contact us if this new schedule does not work for you.',
            $message
        );
    }

    public function test_customer_self_reschedule_does_not_queue_the_team_move_sms(): void
    {
        Notification::fake();
        Queue::fake();
        [, $customer, $service] = $this->seedActors();

        $appointment = $this->makeAppointment(
            $customer,
            $service,
            Carbon::now()->addHours(Appointment::CHANGE_NOTICE_HOURS + 2)->seconds(0),
            'scheduled'
        );
        $movedTo = Carbon::now()->addDays(5)->setTime(13, 0)->seconds(0);

        $this->actingAs($customer)
            ->put(route('appointments.reschedule', $appointment), [
                'appointment_at' => $movedTo->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($movedTo->equalTo($appointment->refresh()->appointment_at));
        Queue::assertNotPushed(SendSmsJob::class);
    }
}
