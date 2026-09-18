<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_pages_use_the_shared_admin_workspace_sidebar(): void
    {
        [$claim] = $this->claim();
        $staff = User::factory()->create(['role' => 'staff']);

        foreach ([route('admin.returns.index'), route('admin.returns.show', $claim)] as $url) {
            $this->actingAs($staff)
                ->get($url)
                ->assertOk()
                ->assertSee('aria-label="Admin navigation"', false)
                ->assertSee('aria-current="page"', false)
                ->assertSeeText('Returns & Replacements');
        }
    }

    public function test_staff_can_review_claim_but_cannot_make_admin_decision(): void
    {
        [$claim] = $this->claim();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.returns.index'))->assertOk()->assertSeeText($claim->claim_number);
        $this->actingAs($staff)->get(route('admin.returns.show', $claim))->assertOk();
        $this->actingAs($staff)->put(route('admin.returns.review', $claim), [
            'action' => 'needs_information',
            'decision_reason' => 'Please add a clear photo of the broken stem.',
        ])->assertRedirect(route('admin.returns.show', $claim));
        $this->assertSame('needs_information', $claim->refresh()->status);

        $this->actingAs($staff)->put(route('admin.returns.decision', $claim), [])->assertForbidden();
    }

    public function test_replacement_approval_decrements_stock_exactly_once(): void
    {
        [$claim, $claimItem, $product] = $this->claim(stock: 3);
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = $this->replacementDecision($claimItem, 2);
        $this->actingAs($admin)
            ->put(route('admin.returns.decision', $claim), $payload)
            ->assertRedirect(route('admin.returns.show', $claim));

        $this->assertSame('approved', $claim->refresh()->status);
        $this->assertSame(1, $product->refresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_REPLACEMENT,
            'quantity' => -2,
            'reference' => $claim->claim_number,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.returns.decision', $claim), $payload)
            ->assertSessionHasErrors('status');
        $this->assertSame(1, $product->refresh()->stock_qty);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_REPLACEMENT)->count());
    }

    public function test_replacement_approval_rolls_back_when_stock_is_insufficient(): void
    {
        [$claim, $claimItem, $product] = $this->claim(stock: 1);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.returns.decision', $claim), $this->replacementDecision($claimItem, 2))
            ->assertSessionHasErrors('items');

        $this->assertSame('submitted', $claim->refresh()->status);
        $this->assertSame(1, $product->refresh()->stock_qty);
        $this->assertDatabaseMissing('stock_movements', ['type' => StockMovement::TYPE_REPLACEMENT]);
    }

    public function test_refund_is_capped_by_approved_amount_and_unrefunded_payment(): void
    {
        [$claim, $claimItem, , $order] = $this->claim(itemPrice: 500);
        $admin = User::factory()->create(['role' => 'admin']);
        Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.returns.decision', $claim), [
            'decision_reason' => 'Refund approved after photo review.',
            'items' => [[
                'id' => $claimItem->id,
                'resolution' => 'partial_refund',
                'refund_amount' => 300,
                'decision_note' => 'Partial refund approved.',
            ]],
        ])->assertRedirect(route('admin.returns.show', $claim));

        $this->actingAs($admin)->post(route('admin.returns.refunds.store', $claim), [
            'amount' => 301,
            'method' => 'gcash',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('refunds', 0);

        $this->actingAs($admin)->post(route('admin.returns.refunds.store', $claim), [
            'amount' => 300,
            'method' => 'gcash',
            'reference' => 'GC-10001',
        ])->assertRedirect(route('admin.returns.show', $claim));

        $this->assertDatabaseHas('refunds', [
            'return_request_id' => $claim->id,
            'amount' => 300,
            'method' => 'gcash',
        ]);
        $this->assertDatabaseMissing('payments', ['amount' => -300]);
        $this->assertSame('resolved', $claim->refresh()->status);
    }

    public function test_staff_can_dispatch_an_approved_replacement_and_resolve_it(): void
    {
        [$claim, $claimItem] = $this->claim(stock: 3);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->put(
            route('admin.returns.decision', $claim),
            $this->replacementDecision($claimItem, 1)
        );

        $this->actingAs($staff)->post(route('admin.returns.dispatch', $claim), [
            'replacement_driver_name' => 'Juan Rider',
            'replacement_driver_phone' => '09171234567',
            'replacement_dispatch_notes' => 'Handle upright.',
        ])->assertRedirect(route('admin.returns.show', $claim));
        $this->assertSame('replacement_dispatched', $claim->refresh()->status);

        $this->actingAs($staff)->post(route('admin.returns.resolve', $claim), [
            'replacement_delivered' => '1',
        ])->assertRedirect(route('admin.returns.show', $claim));
        $this->assertSame('resolved', $claim->refresh()->status);
        $this->assertNotNull($claim->replacement_delivered_at);
    }

    public function test_admin_can_void_a_refund_without_deleting_ledger_history(): void
    {
        [$claim, $claimItem, , $order] = $this->claim(itemPrice: 500);
        $admin = User::factory()->create(['role' => 'admin']);
        Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now(),
        ]);
        $claim->forceFill(['status' => 'approved'])->save();
        $claimItem->update(['resolution' => 'refund', 'refund_amount' => 500]);
        $refund = Refund::query()->create([
            'order_id' => $order->id,
            'return_request_id' => $claim->id,
            'amount' => 500,
            'method' => 'cash',
            'refunded_at' => now(),
            'processed_by' => $admin->id,
        ]);
        $order->update(['payment_status' => 'refunded']);

        $this->actingAs($admin)->put(route('admin.returns.refunds.void', [$claim, $refund]), [
            'void_reason' => 'Cash handover was entered against the wrong claim.',
        ])->assertRedirect(route('admin.returns.show', $claim));

        $this->assertNotNull($refund->refresh()->voided_at);
        $this->assertDatabaseHas('refunds', ['id' => $refund->id]);
        $this->assertSame('partial', $order->refresh()->payment_status);
    }

    public function test_returned_item_is_restocked_only_by_explicit_admin_action_once(): void
    {
        [$claim, $claimItem, $product] = $this->claim(stock: 3);
        $admin = User::factory()->create(['role' => 'admin']);
        $claim->update(['status' => 'approved', 'return_required' => true]);
        $claimItem->update(['disposition' => 'awaiting_return']);

        $this->actingAs($admin)->post(route('admin.returns.items.restock', [$claim, $claimItem]), [
            'quantity' => 1,
        ])->assertRedirect(route('admin.returns.show', $claim));
        $this->assertSame(4, $product->refresh()->stock_qty);
        $this->assertSame('restocked', $claimItem->refresh()->disposition);

        $this->actingAs($admin)->post(route('admin.returns.items.restock', [$claim, $claimItem]), [
            'quantity' => 1,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(4, $product->refresh()->stock_qty);
    }

    public function test_staff_cannot_resolve_before_replacement_delivery_or_approved_refund(): void
    {
        [$replacementClaim, $replacementItem] = $this->claim(stock: 3);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->put(
            route('admin.returns.decision', $replacementClaim),
            $this->replacementDecision($replacementItem, 1)
        );

        $this->actingAs($staff)->post(route('admin.returns.resolve', $replacementClaim), [
            'replacement_delivered' => '1',
        ])->assertSessionHasErrors('status');
        $this->assertSame('approved', $replacementClaim->refresh()->status);

        [$refundClaim, $refundItem] = $this->claim(itemPrice: 400);
        $refundClaim->update(['status' => 'approved']);
        $refundItem->update(['resolution' => 'refund', 'refund_amount' => 400]);
        $this->actingAs($staff)->post(route('admin.returns.resolve', $refundClaim))
            ->assertSessionHasErrors('status');
        $this->assertSame('approved', $refundClaim->refresh()->status);
    }

    public function test_staff_cannot_open_or_mutate_claim_for_archived_order(): void
    {
        [$claim, , , $order] = $this->claim();
        $staff = User::factory()->create(['role' => 'staff']);
        $order->update(['archived_at' => now()]);

        $this->actingAs($staff)->get(route('admin.returns.show', $claim))->assertNotFound();
        $this->actingAs($staff)->get(route('returns.show', $claim))->assertNotFound();
        $this->actingAs($staff)->put(route('admin.returns.review', $claim), [
            'action' => 'under_review',
        ])->assertNotFound();
        $this->assertSame('submitted', $claim->refresh()->status);
    }

    /** @return array{ReturnRequest, ReturnRequestItem, Product, Order} */
    private function claim(int $stock = 5, float $itemPrice = 500): array
    {
        $customer = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'Test Palm '.fake()->unique()->numerify('###'),
            'description' => 'Healthy plant',
            'price' => $itemPrice,
            'stock_qty' => $stock,
            'category' => 'plants',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-'.fake()->unique()->numerify('######'),
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => $itemPrice * 2,
            'delivery_method' => 'delivery',
            'delivered_at' => now()->subHour(),
            'customer_confirmed_at' => now()->subHour(),
        ]);
        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $itemPrice,
            'qty' => 2,
        ]);
        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'customer_summary' => 'The plant arrived with serious damage.',
            'submitted_at' => now(),
        ]);
        $claimItem = $claim->items()->create([
            'order_item_id' => $orderItem->id,
            'quantity_claimed' => 2,
            'issue_type' => 'damaged_on_arrival',
            'issue_description' => 'Two stems are broken.',
            'preferred_resolution' => 'replacement',
        ]);

        return [$claim, $claimItem, $product, $order];
    }

    /** @return array<string, mixed> */
    private function replacementDecision(ReturnRequestItem $item, int $quantity): array
    {
        return [
            'decision_reason' => 'Replacement approved after photo review.',
            'items' => [[
                'id' => $item->id,
                'resolution' => 'replacement',
                'replacement_quantity' => $quantity,
                'decision_note' => 'Replacement approved.',
            ]],
        ];
    }
}
