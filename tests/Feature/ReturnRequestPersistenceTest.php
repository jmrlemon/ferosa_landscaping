<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnRequestPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_and_lines_remain_linked_to_the_original_order_records(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-RETURN-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 1000,
        ]);
        $orderItem = $order->orderItems()->create([
            'name' => 'Areca Palm',
            'price' => 500,
            'qty' => 2,
        ]);

        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-000001',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'customer_summary' => 'One palm arrived with broken stems.',
            'submitted_at' => now(),
        ]);
        $claimItem = $claim->items()->create([
            'order_item_id' => $orderItem->id,
            'quantity_claimed' => 1,
            'issue_type' => 'damaged_on_arrival',
            'issue_description' => 'Several stems were snapped inside the packaging.',
            'preferred_resolution' => 'replacement',
            'disposition' => 'not_required',
        ]);

        $this->assertTrue($claim->order->is($order));
        $this->assertTrue($claim->user->is($customer));
        $this->assertTrue($claimItem->orderItem->is($orderItem));
        $this->assertTrue($order->returnRequests()->firstOrFail()->is($claim));
        $this->assertTrue($customer->returnRequests()->firstOrFail()->is($claim));
        $this->assertTrue($orderItem->returnRequestItems()->firstOrFail()->is($claimItem));
    }

    public function test_claim_number_is_unique(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-RETURN-002',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        ReturnRequest::query()->create([
            'claim_number' => 'RET-000002',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        ReturnRequest::query()->create([
            'claim_number' => 'RET-000002',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}
