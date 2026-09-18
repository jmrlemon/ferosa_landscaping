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

    public function test_approved_refund_names_the_amount_in_app_and_sms(): void
    {
        Notification::fake();
        Queue::fake();
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '09171234567',
            'phone_verified_at' => now(),
        ]);
        $claim = $this->claim($customer, 'approved');
        $this->approvedItem($claim, refundAmount: 250);
        $expectedMessage = "Your claim {$claim->claim_number} was approved for a ₱250.00 refund.";

        app(ReturnRequestNotifier::class)->notify($claim, 'approved');

        Notification::assertSentTo(
            $customer,
            ReturnRequestUpdated::class,
            fn (ReturnRequestUpdated $notification): bool => $notification->toArray($customer)['message'] === $expectedMessage
        );
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->recipient() === '+639171234567'
            && $job->message() === "Ferosa: {$expectedMessage} View your account for details.");
    }

    public function test_approved_replacement_names_the_quantity_in_app_and_sms(): void
    {
        Notification::fake();
        Queue::fake();
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '09171234567',
            'phone_verified_at' => now(),
        ]);
        $claim = $this->claim($customer, 'approved');
        $this->approvedItem($claim, replacementQuantity: 1);
        $expectedMessage = "Your claim {$claim->claim_number} was approved for 1 replacement item.";

        app(ReturnRequestNotifier::class)->notify($claim, 'approved');

        Notification::assertSentTo(
            $customer,
            ReturnRequestUpdated::class,
            fn (ReturnRequestUpdated $notification): bool => $notification->toArray($customer)['message'] === $expectedMessage
        );
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->recipient() === '+639171234567'
            && $job->message() === "Ferosa: {$expectedMessage} View your account for details.");
    }

    public function test_mixed_approval_names_both_customer_outcomes(): void
    {
        Notification::fake();
        Queue::fake();
        $customer = User::factory()->create([
            'role' => 'user',
            'phone_number' => '09171234567',
            'phone_verified_at' => now(),
        ]);
        $claim = $this->claim($customer, 'approved');
        $this->approvedItem($claim, refundAmount: 250);
        $this->approvedItem($claim, replacementQuantity: 2);
        $expectedMessage = "Your claim {$claim->claim_number} was approved for a ₱250.00 refund and 2 replacement items.";

        app(ReturnRequestNotifier::class)->notify($claim, 'approved');

        Notification::assertSentTo(
            $customer,
            ReturnRequestUpdated::class,
            fn (ReturnRequestUpdated $notification): bool => $notification->toArray($customer)['message'] === $expectedMessage
        );
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message() === "Ferosa: {$expectedMessage} View your account for details.");
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

    private function approvedItem(
        ReturnRequest $claim,
        float $refundAmount = 0,
        int $replacementQuantity = 0,
    ): void {
        $orderItem = $claim->order->orderItems()->create([
            'name' => 'Areca Palm',
            'price' => 250,
            'qty' => max(1, $replacementQuantity),
        ]);

        $claim->items()->create([
            'order_item_id' => $orderItem->id,
            'quantity_claimed' => max(1, $replacementQuantity),
            'issue_type' => 'damaged_on_arrival',
            'issue_description' => 'The plant arrived damaged.',
            'preferred_resolution' => $replacementQuantity > 0 ? 'replacement' : 'refund',
            'resolution' => $replacementQuantity > 0 ? 'replacement' : 'refund',
            'replacement_quantity' => $replacementQuantity,
            'refund_amount' => $refundAmount,
        ]);
    }
}
