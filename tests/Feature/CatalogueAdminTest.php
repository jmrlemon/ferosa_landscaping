<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Services and products are the catalogue the whole business is priced from,
 * and they were the last untested corner of the admin workspace. Two rules
 * carry the weight here: nothing is ever hard deleted - archiving keeps the
 * row so past orders and the audit trail still point at something real - and
 * the uniqueness of a name is scoped to what is not archived, so a name can be
 * reused after its holder is retired.
 */
class CatalogueAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    // -- Services ------------------------------------------------------------

    public function test_an_admin_can_add_a_service_and_it_is_audited(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.services.store'), [
                'name' => 'Tree Surgery',
                'default_fee' => '2500',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.dashboard', ['tab' => 'services']));

        $service = ServiceType::query()->where('name', 'Tree Surgery')->firstOrFail();
        $this->assertSame(2500.0, (float) $service->default_fee);
        $this->assertTrue((bool) $service->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.create']);
    }

    public function test_a_service_name_is_unique_only_among_the_live_ones(): void
    {
        $admin = $this->admin();
        $existing = ServiceType::query()->create([
            'name' => 'Lawn Care',
            'default_fee' => 900,
            'is_active' => true,
        ]);

        // While it is live, the name is taken.
        $this->actingAs($admin)
            ->post(route('admin.services.store'), ['name' => 'Lawn Care', 'default_fee' => '1000'])
            ->assertSessionHasErrors('name');

        // Archive it, and the name frees up for a replacement.
        $this->actingAs($admin)->delete(route('admin.services.delete', $existing));
        $this->assertNotNull($existing->refresh()->archived_at);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), ['name' => 'Lawn Care', 'default_fee' => '1000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ServiceType::query()->where('name', 'Lawn Care')->count());
    }

    public function test_archiving_a_service_keeps_the_row_and_restore_brings_it_back(): void
    {
        $admin = $this->admin();
        $service = ServiceType::query()->create([
            'name' => 'Hardscaping',
            'default_fee' => 3000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->delete(route('admin.services.delete', $service));
        $this->assertNotNull($service->refresh()->archived_at);

        // Never hard deleted: past appointments and audit entries point here.
        $this->assertDatabaseHas('service_types', ['id' => $service->id]);

        $this->actingAs($admin)->put(route('admin.services.restore', $service));
        $this->assertNull($service->refresh()->archived_at);

        // Restoring something that was never archived is not a valid request.
        $this->actingAs($admin)
            ->put(route('admin.services.restore', $service))
            ->assertNotFound();

        $this->assertDatabaseHas('audit_logs', ['action' => 'service.archive']);
    }

    public function test_an_archived_service_cannot_be_prepared_for_booking(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $retired = ServiceType::query()->create(['name' => 'Hardscaping Quote', 'default_fee' => 1800, 'is_active' => true]);

        $this->actingAs($admin)->delete(route('admin.services.delete', $retired));

        $this->actingAs($customer)
            ->from(route('estimator'))
            ->post(route('estimator.prepare'), [
                'project_type' => 'hardscaping',
                'size' => 100,
                'tier' => 'standard',
            ])
            ->assertRedirect(route('estimator'))
            ->assertSessionHasErrors('project_type')
            ->assertSessionMissing('estimator_booking');
    }

    // -- Products ------------------------------------------------------------

    public function test_creating_a_product_books_its_opening_stock_in_one_transaction(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), [
                'name' => 'Bermuda Grass',
                'price' => '120',
                'stock_qty' => '25',
                'category' => 'Plants',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'Bermuda Grass')->firstOrFail();
        $this->assertSame(25, (int) $product->stock_qty);

        // The opening stock is a ledger movement, not just a column value.
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 25,
            'quantity_after' => 25,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.create']);
    }

    public function test_a_product_name_is_unique_only_among_the_live_ones(): void
    {
        $admin = $this->admin();
        $existing = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 10,
            'category' => 'Materials',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'Garden Soil',
                'price' => '300',
                'category' => 'Materials',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->delete(route('admin.products.delete', $existing));

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'name' => 'Garden Soil',
                'price' => '300',
                'category' => 'Materials',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_archiving_a_product_hides_it_from_the_shop_without_deleting_it(): void
    {
        $admin = $this->admin();
        $product = Product::query()->create([
            'name' => 'Fertiliser',
            'price' => 180,
            'stock_qty' => 12,
            'category' => 'Materials',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->delete(route('admin.products.delete', $product));
        $this->assertNotNull($product->refresh()->archived_at);
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // Past orders reference this row, so it has to survive being retired.
        $this->get(route('shop'))->assertOk()->assertDontSee('Fertiliser');

        $this->actingAs($admin)->put(route('admin.products.restore', $product));
        $this->assertNull($product->refresh()->archived_at);
        $this->get(route('shop'))->assertOk()->assertSee('Fertiliser');
    }

    public function test_the_catalogue_is_closed_to_customers(): void
    {
        $customer = $this->customer();
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 10,
            'category' => 'Materials',
            'is_active' => true,
        ]);
        $service = ServiceType::query()->create(['name' => 'Lawn Care', 'default_fee' => 900, 'is_active' => true]);

        $this->actingAs($customer)
            ->post(route('admin.products.store'), ['name' => 'Free Stuff', 'price' => '0', 'category' => 'Plants'])
            ->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.products.delete', $product))->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.services.delete', $service))->assertForbidden();

        $this->assertNull($product->refresh()->archived_at);
        $this->assertNull($service->refresh()->archived_at);
    }
}
