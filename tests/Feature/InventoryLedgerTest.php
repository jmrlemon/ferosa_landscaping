<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_records_a_sale_movement(): void
    {
        Mail::fake();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 20);

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ])->assertOk();

        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $product->refresh();

        $this->assertSame(15, $product->stock_qty);

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_SALE)
            ->firstOrFail();

        $this->assertSame(-5, $movement->quantity);
        $this->assertSame(15, $movement->quantity_after);
        $this->assertSame($order->order_number, $movement->reference);
    }

    public function test_cancelling_an_order_records_a_return_movement(): void
    {
        Mail::fake();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 10);

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(7, $product->refresh()->stock_qty);

        $this->actingAs($customer)
            ->delete(route('orders.cancel', $order), ['cancel_reason' => 'Changed my mind.'])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(10, $product->stock_qty);

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_RETURN)
            ->firstOrFail();

        $this->assertSame(3, $movement->quantity);
        $this->assertSame(10, $movement->quantity_after);
        $this->assertSame($order->order_number, $movement->reference);
    }

    public function test_admin_can_restock_with_a_supplier_and_unit_cost(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 2);

        $this->actingAs($admin)
            ->post(route('admin.inventory.restock', $product), [
                'quantity' => 50,
                'supplier' => 'Bataan Supply',
                'unit_cost' => 90.00,
                'note' => 'Weekly delivery.',
            ])->assertRedirect();

        $product->refresh();
        $this->assertSame(52, $product->stock_qty);

        $movement = StockMovement::query()
            ->where('type', StockMovement::TYPE_RESTOCK)
            ->firstOrFail();

        $this->assertSame(50, $movement->quantity);
        $this->assertSame(52, $movement->quantity_after);
        $this->assertSame('Bataan Supply', $movement->supplier);
        $this->assertSame(4500.00, $movement->totalCost());
        $this->assertSame($admin->id, $movement->user_id);
    }

    public function test_wastage_is_always_a_write_off_and_needs_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        // A positive number submitted as wastage still removes stock.
        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_WASTAGE,
                'quantity' => 3,
                'note' => 'Spoiled after the rain.',
            ])->assertRedirect();

        $this->assertSame(7, $product->refresh()->stock_qty);
        $this->assertSame(-3, StockMovement::query()->where('type', StockMovement::TYPE_WASTAGE)->firstOrFail()->quantity);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_WASTAGE,
                'quantity' => 1,
            ])->assertSessionHasErrors('note');

        $this->assertSame(7, $product->refresh()->stock_qty);
    }

    public function test_stock_cannot_be_driven_below_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 4);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_WASTAGE,
                'quantity' => 9,
                'note' => 'Attempted over-write-off.',
            ])->assertSessionHasErrors('quantity');

        $this->assertSame(4, $product->refresh()->stock_qty);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_WASTAGE)->count());
    }

    public function test_the_product_form_cannot_change_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Renamed Soil',
                'price' => 175,
                // Posted by hand: the form no longer renders this field, and the
                // controller must ignore it rather than move stock without a reason.
                'stock_qty' => 8,
                'category' => 'materials',
                'is_active' => 1,
            ])->assertRedirect();

        $product->refresh();
        $this->assertSame('Renamed Soil', $product->name);
        $this->assertSame('175.00', $product->price);
        // Stock is untouched and no phantom correction was written.
        $this->assertSame(10, $product->stock_qty);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_CORRECTION)->count());
    }

    public function test_product_edit_hides_the_embedded_history_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee('Admin navigation')
            ->assertSee('View history')
            ->assertDontSee('Movement history');

        $this->actingAs($admin)
            ->get(route('admin.inventory.show', $product))
            ->assertOk()
            ->assertSee('Movement history');

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Back to '.$product->name)
            ->assertSee(route('admin.products.edit', $product), false);
    }

    public function test_a_correction_takes_the_counted_level_not_the_difference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        // The admin counted 8 on the shelf and types 8 - not -2.
        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_CORRECTION,
                'quantity' => 8,
                'note' => 'Physical count found two fewer sacks.',
            ])->assertRedirect();

        $product->refresh();
        $this->assertSame(8, $product->stock_qty);

        $movement = StockMovement::query()->where('type', StockMovement::TYPE_CORRECTION)->firstOrFail();
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(8, $movement->quantity_after);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame('Physical count found two fewer sacks.', $movement->note);
    }

    public function test_a_correction_can_also_raise_the_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_CORRECTION,
                'quantity' => 14,
                'note' => 'Found a pallet that was never counted.',
            ])->assertRedirect();

        $this->assertSame(14, $product->refresh()->stock_qty);
        $this->assertSame(4, StockMovement::query()->where('type', StockMovement::TYPE_CORRECTION)->firstOrFail()->quantity);
    }

    public function test_a_correction_to_the_current_level_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 10);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), [
                'type' => StockMovement::TYPE_CORRECTION,
                'quantity' => 10,
                'note' => 'Counted the same.',
            ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_CORRECTION)->count());
    }

    public function test_creating_a_product_books_its_opening_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'River Pebbles',
                'price' => 200,
                'stock_qty' => 30,
                'category' => 'materials',
                'is_active' => 1,
            ])->assertRedirect();

        $product = Product::query()->where('name', 'River Pebbles')->firstOrFail();
        $this->assertSame(30, $product->stock_qty);

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_OPENING)
            ->firstOrFail();

        $this->assertSame(30, $movement->quantity);
        $this->assertSame(30, $movement->quantity_after);
    }

    public function test_the_ledger_reconciles_to_the_current_stock_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 0);
        $inventory = app(InventoryService::class);

        $inventory->record($product, StockMovement::TYPE_RESTOCK, 50, ['user_id' => $admin->id]);
        $inventory->recordSale($product->refresh(), 12, 'FRS-TEST-1');
        $inventory->record($product->refresh(), StockMovement::TYPE_WASTAGE, -3, ['user_id' => $admin->id]);
        $inventory->recordReturn($product->refresh(), 4, 'FRS-TEST-1');

        $product->refresh();

        // Opening (0) + 50 - 12 - 3 + 4 = 39
        $this->assertSame(39, $product->stock_qty);
        $this->assertSame(39, (int) StockMovement::query()->where('product_id', $product->id)->sum('quantity'));
        $this->assertSame(39, $product->stockMovements()->first()->quantity_after);
    }

    public function test_a_zero_quantity_movement_is_rejected(): void
    {
        $product = $this->product(stock: 5);

        $this->expectException(RuntimeException::class);
        app(InventoryService::class)->record($product, StockMovement::TYPE_RESTOCK, 0);
    }

    public function test_only_admins_reach_the_inventory_ledger(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($staff)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.inventory.index'))->assertOk();
    }

    private function product(int $stock): Product
    {
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 150,
            'stock_qty' => $stock,
            'category' => 'materials',
            'is_active' => true,
        ]);

        // Created directly to set the starting level; the ledger under test only
        // covers movements from here on.
        return $product;
    }
}
