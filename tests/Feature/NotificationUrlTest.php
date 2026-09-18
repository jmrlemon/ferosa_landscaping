<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\WorkCreatedNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_api_repairs_a_stale_localhost_click_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notifyNow(new WorkCreatedNotice(
            type: 'appointment_created',
            message: 'New consultation needs review.',
            url: 'http://localhost:8000/admin/service-scheduling/17',
            appointmentId: 17,
        ));

        $this->actingAs($admin)
            ->getJson(route('notifications'))
            ->assertOk()
            ->assertJsonPath(
                'notifications.0.data.url',
                '/admin/service-scheduling/17',
            );
    }

    public function test_notification_api_never_returns_an_external_redirect_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notifyNow(new WorkCreatedNotice(
            type: 'appointment_created',
            message: 'New consultation needs review.',
            url: 'https://malicious.example//phishing',
            appointmentId: 17,
        ));

        $this->actingAs($admin)
            ->getJson(route('notifications'))
            ->assertOk()
            ->assertJsonPath(
                'notifications.0.data.url',
                route('home', absolute: false),
            );
    }

    public function test_new_booking_notification_stores_a_relative_admin_route(): void
    {
        Mail::fake();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $service = ServiceType::query()->create([
            'name' => 'Garden Consultation',
            'default_fee' => 750,
            'is_active' => true,
        ]);
        $appointmentAt = Carbon::now()
            ->next(Carbon::MONDAY)
            ->setTime(10, 30)
            ->seconds(0);

        $this->actingAs($customer)
            ->withSession($this->estimatorBookingSession($service))
            ->post(route('schedule.store'), [
                'service_type_id' => $service->id,
                'appointment_at' => $appointmentAt->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->where('user_id', $customer->id)->firstOrFail();
        Notification::assertSentTo(
            $staff,
            WorkCreatedNotice::class,
            fn (WorkCreatedNotice $notification): bool => $notification->toArray($staff)['url']
                === route('admin.appointments.show', $appointment, absolute: false),
        );
    }
}
