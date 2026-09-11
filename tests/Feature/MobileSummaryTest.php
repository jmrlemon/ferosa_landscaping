<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers GET /api/mobile/summary — the snapshot behind the Android app's Home
 * screen, its navigation badges, and the background poll that raises local
 * notifications.
 *
 * The scoping assertions matter most: this endpoint takes no id from the
 * request, so a leak here would be a leak of another customer's order history.
 */
class MobileSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_summary(): void
    {
        $this->getJson('/api/mobile/summary')->assertUnauthorized();
    }

    public function test_a_new_customer_gets_an_empty_summary(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->getJson('/api/mobile/summary')
            ->assertOk()
            ->assertJson([
                'cart_count' => 0,
                'unread_notifications' => 0,
                'unread_messages' => 0,
                'active_order' => null,
                'next_appointment' => null,
            ]);
    }

    public function test_summary_reports_the_customers_own_cart_order_appointment_and_messages(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product();

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-SUMMARY-1',
            'status' => 'out_for_delivery',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'total_amount' => 1250,
        ]);

        $serviceType = ServiceType::query()->create([
            'name' => 'Lawn Care',
            'default_fee' => 800,
            'is_active' => true,
        ]);

        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'appointment_at' => now()->addDays(3),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'appointment_amount' => 800,
        ]);

        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'last_message_at' => now(),
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => 'We are on our way.',
        ]);

        $response = $this->actingAs($customer)
            ->getJson('/api/mobile/summary')
            ->assertOk();

        $response->assertJson([
            'cart_count' => 3,
            'unread_messages' => 1,
            'active_order' => [
                'id' => $order->id,
                'order_number' => 'FRS-SUMMARY-1',
                'status' => 'out_for_delivery',
                // `out_for_delivery` has to be readable on a phone screen.
                'status_label' => 'Out for delivery',
            ],
            'next_appointment' => [
                'id' => $appointment->id,
                'service' => 'Lawn Care',
                'status' => 'confirmed',
            ],
        ]);
    }

    public function test_one_customers_summary_never_shows_another_customers_records(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $otherCustomer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product();

        // Everything below belongs to $otherCustomer.
        $this->actingAs($otherCustomer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        Order::query()->create([
            'user_id' => $otherCustomer->id,
            'order_number' => 'FRS-SUMMARY-OTHER',
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'total_amount' => 999,
        ]);

        $otherConversation = Conversation::query()->create([
            'customer_id' => $otherCustomer->id,
            'last_message_at' => now(),
        ]);
        Message::query()->create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $admin->id,
            'body' => 'Private reply for someone else.',
        ]);

        $this->actingAs($customer)
            ->getJson('/api/mobile/summary')
            ->assertOk()
            ->assertJson([
                'cart_count' => 0,
                'unread_messages' => 0,
                'active_order' => null,
                'next_appointment' => null,
            ]);
    }

    public function test_completed_orders_and_past_appointments_are_not_reported_as_active(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-SUMMARY-DONE',
            'status' => 'completed',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        $serviceType = ServiceType::query()->create([
            'name' => 'Pruning',
            'default_fee' => 400,
            'is_active' => true,
        ]);

        Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'appointment_at' => now()->subWeek(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'appointment_amount' => 400,
        ]);

        $this->actingAs($customer)
            ->getJson('/api/mobile/summary')
            ->assertOk()
            ->assertJson([
                'active_order' => null,
                'next_appointment' => null,
            ]);
    }

    /**
     * `delivered` still needs the customer to confirm receipt, so the app must
     * keep surfacing it. This is the one status where "finished for us" and
     * "finished for them" disagree.
     */
    public function test_delivered_orders_are_still_active_until_receipt_is_confirmed(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-SUMMARY-DELIVERED',
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'total_amount' => 700,
        ]);

        $this->actingAs($customer)
            ->getJson('/api/mobile/summary')
            ->assertOk()
            ->assertJsonPath('active_order.status', 'delivered');
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Summary Test Plant',
            'price' => 150,
            'stock_qty' => 20,
            'category' => 'Plants',
            'is_active' => true,
        ]);
    }
}
