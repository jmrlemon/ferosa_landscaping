<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\ReturnRequestUpdated;
use App\Services\ReturnRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReturnRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_important_update_queues_in_app_and_sms_for_verified_phone(): void
    {
        Notification::fake();
        Queue::fake();
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '09171234567',
            'phone_verified_at' => now(),
        ]);
        $claim = $this->claim($customer, 'approved');

        app(ReturnRequestNotifier::class)->notify($claim, 'approved');

        Notification::assertSentTo($customer, ReturnRequestUpdated::class);
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->recipient() === '+639171234567'
            && str_contains($job->message(), $claim->claim_number)
            && str_contains($job->message(), 'approved'));
    }

    public function test_unverified_phone_keeps_in_app_update_without_sms(): void
    {
        Notification::fake();
        Queue::fake();
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '09171234567',
            'phone_verified_at' => null,
        ]);
        $claim = $this->claim($customer, 'needs_information');

        app(ReturnRequestNotifier::class)->notify($claim, 'needs_information');

        Notification::assertSentTo($customer, ReturnRequestUpdated::class);
        Queue::assertNotPushed(SendSmsJob::class);
    }

    private function claim(User $customer, string $status): ReturnRequest
    {
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-'.fake()->unique()->numerify('######'),
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        return ReturnRequest::query()->create([
            'claim_number' => 'RET-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }
}
