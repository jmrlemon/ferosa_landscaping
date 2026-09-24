<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\PhilippineDiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductDiscountEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_migration_does_not_auto_approve_items_for_statutory_discount(): void
    {
        $product = Product::create([
            'name' => 'Garden Stone',
            'price' => '500.00',
            'stock_qty' => 10,
            'category' => 'stones',
            'is_active' => true,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE,
        ]);
        $service = ServiceType::create([
            'name' => 'Garden Design',
            'default_fee' => '1500.00',
            'is_active' => true,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE,
        ]);

        $migration = require database_path('migrations/2026_09_24_000004_enable_confirmed_statutory_discount_catalogue.php');
        $migration->up();

        $this->assertSame(PhilippineDiscountCalculator::SCHEME_NONE, $product->fresh()->discount_scheme);
        $this->assertSame(PhilippineDiscountCalculator::SCHEME_NONE, $service->fresh()->discount_scheme);
    }

    public function test_admin_can_classify_a_product_and_checkout_snapshots_that_classification(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'Qualified Fertilizer',
                'price' => '500.00',
                'stock_qty' => 10,
                'category' => 'materials',
                'discount_scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Qualified Fertilizer')->firstOrFail();
        $this->assertSame(PhilippineDiscountCalculator::SCHEME_BNPC_5, $product->discount_scheme);

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(PhilippineDiscountCalculator::SCHEME_BNPC_5, $order->orderItems()->firstOrFail()->discount_scheme);
        $this->assertSame(PhilippineDiscountCalculator::SCHEME_BNPC_5, $order->items[0]['discount_scheme']);
    }

    public function test_unknown_product_discount_scheme_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'Invalid Discount Item',
                'price' => '500.00',
                'stock_qty' => 10,
                'category' => 'materials',
                'discount_scheme' => 'twenty_percent_everything',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('discount_scheme');

        $this->assertDatabaseMissing('products', ['name' => 'Invalid Discount Item']);
    }

    public function test_product_forms_explain_that_eligibility_needs_compliance_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Philippine discount eligibility')
            ->assertSee('Do not mark an item eligible without accountant or BIR confirmation.');
    }
}
