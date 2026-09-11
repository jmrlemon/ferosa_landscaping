<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceiptVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_receipt_is_hidden_and_inaccessible_until_the_order_is_completed(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-RECEIPT-PENDING',
            'status' => 'pending',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        foreach (['pending', 'confirmed', 'out_for_delivery', 'delivered', 'cancelled'] as $status) {
            $order->update(['status' => $status]);
            $receiptUrl = route('orders.receipt', $order);

            $this->actingAs($customer)
                ->get(route('orders'))
                ->assertOk()
                ->assertDontSee($receiptUrl, false);

            $this->actingAs($customer)
                ->get($receiptUrl)
                ->assertNotFound();
        }
    }

    public function test_completed_order_receipt_is_visible_and_accessible_to_its_owner(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-RECEIPT-COMPLETE',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);
        $receiptUrl = route('orders.receipt', $order);

        $this->actingAs($customer)
            ->get(route('orders'))
            ->assertOk()
            ->assertSee($receiptUrl, false);

        $this->actingAs($customer)
            ->get($receiptUrl)
            ->assertOk()
            ->assertSeeText('FRS-RECEIPT-COMPLETE');
    }

    public function test_completed_order_receipt_remains_private_to_its_owner(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $owner->id,
            'order_number' => 'FRS-RECEIPT-PRIVATE',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        $this->actingAs($stranger)
            ->get(route('orders.receipt', $order))
            ->assertForbidden();
    }
}
