<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\AppointmentScopeUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The account page and the feedback form are the two places a customer writes
 * to their own records. Both were unguarded by tests, and both have a rule
 * worth keeping honest: a password change has to prove the old password, and a
 * review has to belong to something the reviewer actually completed.
 */
class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function completedAppointment(User $customer): Appointment
    {
        $service = ServiceType::query()->create([
            'name' => 'Lawn Care',
            'default_fee' => 900,
            'is_active' => true,
        ]);
        $at = Carbon::now()->subDays(2)->setTime(9, 0);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $at,
            'appointment_amount' => 900,
            'status' => 'completed',
        ]);
    }

    public function test_a_customer_can_update_their_profile(): void
    {
        $customer = User::factory()->create(['role' => 'user', 'name' => 'Old Name']);

        $this->actingAs($customer)
            ->put(route('account.update'), [
                'name' => 'Maria Santos',
                'email' => 'maria@example.com',
                'phone_number' => '09171234567',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Maria Santos', $customer->name);
        $this->assertSame('maria@example.com', $customer->email);
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $customer = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('correct-horse'),
        ]);

        $this->actingAs($customer)
            ->put(route('account.update'), [
                'name' => $customer->name,
                'email' => $customer->email,
                'current_password' => 'not-the-password',
                'password' => 'a-brand-new-one',
                'password_confirmation' => 'a-brand-new-one',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-horse', $customer->refresh()->password));

        $this->actingAs($customer)
            ->put(route('account.update'), [
                'name' => $customer->name,
                'email' => $customer->email,
                'current_password' => 'correct-horse',
                'password' => 'a-brand-new-one',
                'password_confirmation' => 'a-brand-new-one',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-brand-new-one', $customer->refresh()->password));
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        User::factory()->create(['role' => 'user', 'email' => 'taken@example.com']);
        $customer = User::factory()->create(['role' => 'user', 'email' => 'mine@example.com']);

        $this->actingAs($customer)
            ->put(route('account.update'), [
                'name' => $customer->name,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('mine@example.com', $customer->refresh()->email);
    }

    public function test_feedback_must_belong_to_something_the_reviewer_completed(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $appointment = $this->completedAppointment($customer);

        // Not connected to anything.
        $this->actingAs($customer)
            ->post(route('feedback.store'), ['rating' => 5, 'comment' => 'Great work'])
            ->assertSessionHasErrors('feedback');

        // Someone else's visit.
        $this->actingAs($stranger)
            ->post(route('feedback.store'), [
                'rating' => 1,
                'appointment_id' => $appointment->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, Feedback::query()->count());
    }

    public function test_a_completed_visit_can_be_reviewed_once_and_carries_its_service(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $appointment = $this->completedAppointment($customer);

        $this->actingAs($customer)
            ->post(route('feedback.store'), [
                'rating' => 5,
                'comment' => 'The crew was on time.',
                'appointment_id' => $appointment->id,
            ])
            ->assertSessionHasNoErrors();

        $feedback = Feedback::query()->firstOrFail();
        $this->assertSame(5, (int) $feedback->rating);
        $this->assertSame($customer->id, (int) $feedback->user_id);

        // The rating belongs to the service that was actually performed, and is
        // never taken from the form.
        $this->assertSame((int) $appointment->service_type_id, (int) $feedback->service_type_id);
        $this->assertNull($feedback->product_id);

        // A second review of the same visit does not stack.
        $this->actingAs($customer)
            ->post(route('feedback.store'), [
                'rating' => 1,
                'appointment_id' => $appointment->id,
            ]);

        $this->assertSame(1, Feedback::query()->count());
    }

    public function test_order_feedback_returns_to_the_page_where_it_was_submitted(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-FEEDBACK-1',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        $this->actingAs($customer)
            ->from(route('orders'))
            ->post(route('feedback.store'), [
                'rating' => 5,
                'comment' => 'Delivered carefully.',
                'order_id' => $order->id,
            ])
            ->assertRedirect(route('orders'))
            ->assertSessionHas('status', 'Thank you for your feedback!');

        $this->assertDatabaseHas('feedbacks', [
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => 5,
        ]);
    }

    public function test_a_customer_only_marks_their_own_notifications_read(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);

        $stranger->notify(new AppointmentScopeUpdated($this->completedAppointment($stranger)));
        $notification = $stranger->notifications()->firstOrFail();

        // The id is looked up through the caller's own notifications, so
        // someone else's simply is not found - and nothing is marked.
        $this->actingAs($customer)
            ->post(route('notifications.read', $notification->id))
            ->assertOk();

        $this->assertNull($stranger->notifications()->first()->read_at);

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notification->id))
            ->assertOk();

        $this->assertNotNull($stranger->notifications()->first()->read_at);
    }
}
