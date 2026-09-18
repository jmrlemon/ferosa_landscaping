<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EstimatedDeliveryDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_a_date_only_delivery_estimate(): void
    {
        Carbon::setTestNow('2026-09-18 14:30:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveryOrderFor($customer);

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'pending',
                'estimated_delivery_date' => '2026-09-21',
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('2026-09-21', $order->estimated_delivery_date?->toDateString());

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('name="estimated_delivery_date"', false)
            ->assertSee('type="date"', false)
            ->assertSee('min="2026-09-18"', false)
            ->assertSee('value="2026-09-21"', false);
    }

    public function test_active_order_rejects_a_past_delivery_estimate(): void
    {
        Carbon::setTestNow('2026-09-18 14:30:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->deliveryOrderFor(User::factory()->create(['role' => 'user']));

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'pending',
                'estimated_delivery_date' => '2026-09-17',
            ])
            ->assertSessionHasErrors('estimated_delivery_date');

        $this->assertNull($order->fresh()->estimated_delivery_date);
    }

    public function test_customer_sees_the_estimate_only_while_delivery_is_active(): void
    {
        Carbon::setTestNow('2026-09-18 14:30:00');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveryOrderFor($customer);
        $order->forceFill(['estimated_delivery_date' => '2026-09-21'])->save();

        $this->actingAs($customer)
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('Estimated delivery')
            ->assertSeeText('Sep 21, 2026');

        $order->forceFill([
            'status' => 'delivered',
            'delivered_at' => now(),
        ])->save();

        $this->actingAs($customer)
            ->get(route('orders'))
            ->assertOk()
            ->assertDontSeeText('Estimated delivery')
            ->assertDontSeeText('Sep 21, 2026');
    }

    public function test_pickup_orders_cannot_store_a_delivery_estimate(): void
    {
        Carbon::setTestNow('2026-09-18 14:30:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->deliveryOrderFor(User::factory()->create(['role' => 'user']));
        $order->forceFill(['delivery_method' => 'pickup'])->save();

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'pending',
                'estimated_delivery_date' => '2026-09-21',
            ])
            ->assertSessionHasErrors('estimated_delivery_date');

        $this->assertNull($order->fresh()->estimated_delivery_date);
    }

    private function deliveryOrderFor(User $customer): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-EST-'.str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 500,
            'items' => [],
            'delivery_method' => 'delivery',
            'payment_method' => 'cod',
        ]);
    }
}
