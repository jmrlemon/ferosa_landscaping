<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Project;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Renders every admin surface so refactors of the workspace/dashboard have a
 * regression net. These assert the pages come back 200 and are not silently
 * rendering a Laravel error page.
 */
class AdminWorkspaceRenderTest extends TestCase
{
    use RefreshDatabase;

    /** Every tab the dashboard exposes. */
    public const TABS = [
        'overview', 'appointments', 'orders', 'services', 'products',
        'payment', 'archived', 'audit', 'users', 'feedbacks', 'messages',
    ];

    private function seedWorkload(): array
    {
        $customer = User::factory()->create(['role' => 'user', 'name' => 'Ana Reyes']);

        $product = Product::query()->create([
            'name' => 'Boston Fern', 'category' => 'plants', 'price' => 450,
            'stock_qty' => 3, 'is_active' => true, 'description' => 'Shade fern.',
        ]);
        // Archived rows exercise the archived tab.
        Product::query()->create([
            'name' => 'Retired Spade', 'category' => 'tools', 'price' => 300,
            'stock_qty' => 0, 'is_active' => false, 'archived_at' => now(),
        ]);

        $service = ServiceType::query()->create([
            'name' => 'Lawn Care', 'default_fee' => 1500, 'is_active' => true,
        ]);
        ServiceType::query()->create([
            'name' => 'Retired Service', 'default_fee' => 900,
            'is_active' => false, 'archived_at' => now(),
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id, 'order_number' => 'FRS-50001',
            'status' => 'pending', 'total_amount' => 450,
            'items' => [['name' => 'Boston Fern', 'qty' => 1, 'price' => 450]],
        ]);
        Order::query()->create([
            'user_id' => $customer->id, 'order_number' => 'FRS-50002',
            'status' => 'completed', 'total_amount' => 900,
            'items' => [['name' => 'Boston Fern', 'qty' => 2, 'price' => 450]],
            'archived_at' => now(),
        ]);

        $appointment = Appointment::query()->create([
            'user_id' => $customer->id, 'service_type_id' => $service->id,
            'appointment_at' => Carbon::now()->addDays(2), 'status' => 'scheduled',
        ]);
        Appointment::query()->create([
            'user_id' => $customer->id, 'service_type_id' => $service->id,
            'appointment_at' => Carbon::now()->subDays(9), 'status' => 'completed',
            'archived_at' => now(),
        ]);

        $project = Project::query()->create([
            'title' => 'Courtyard Renewal', 'slug' => 'courtyard-renewal',
            'summary' => 'A completed courtyard project.', 'is_published' => true,
        ]);

        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id, 'last_message_at' => now(),
        ]);

        return compact('customer', 'product', 'service', 'order', 'appointment', 'project', 'conversation');
    }

    private function assertRenders(string $url): void
    {
        $response = $this->get($url);

        $this->assertSame(200, $response->status(), "Expected 200 from {$url}, got {$response->status()}");
        $this->assertStringNotContainsString('Whoops', $response->getContent(), "Error page rendered at {$url}");
    }

    public function test_admin_can_render_every_dashboard_tab(): void
    {
        $data = $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (self::TABS as $tab) {
            $this->assertRenders('/admin?tab='.$tab);
        }

        // Dedicated module URLs that currently alias the dashboard.
        $this->assertRenders('/admin/service-scheduling');
        $this->assertRenders('/admin/ordering-delivery');
    }

    /**
     * Guards the failure mode a status-code check cannot see: a tab that still
     * returns 200 but has lost the data it is supposed to list. Each tab must
     * actually contain its own records.
     */
    public function test_each_tab_still_carries_its_own_data(): void
    {
        $data = $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $expectations = [
            'products' => ['Boston Fern'],
            'orders' => ['FRS-50001'],
            'appointments' => ['Lawn Care', 'Ana Reyes'],
            'services' => ['Lawn Care'],
            'users' => ['Ana Reyes'],
            'archived' => ['Retired Spade', 'Retired Service'],
            'feedbacks' => [],
            'overview' => ['FRS-50001'],
        ];

        foreach ($expectations as $tab => $needles) {
            $html = $this->get('/admin?tab='.$tab)->getContent();

            foreach ($needles as $needle) {
                $this->assertStringContainsString(
                    $needle,
                    $html,
                    "Tab '{$tab}' no longer lists '{$needle}' — its data was dropped."
                );
            }
        }
    }

    public function test_each_dashboard_tab_has_an_explicit_server_rendered_visibility_state(): void
    {
        $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $tabs = array_values(array_diff(self::TABS, ['messages']));

        foreach ($tabs as $activeTab) {
            $html = $this->get('/admin?tab='.$activeTab)->getContent();
            $document = new \DOMDocument;
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

            foreach ($tabs as $tab) {
                $panel = $document->getElementById('tab-'.$tab);

                $this->assertNotNull($panel, "Tab panel '{$tab}' is missing.");
                $this->assertSame(
                    $tab === $activeTab ? 'display:block' : 'display:none',
                    $panel->getAttribute('style'),
                    "Tab '{$tab}' has the wrong visibility while '{$activeTab}' is active."
                );
            }
        }
    }

    public function test_admin_can_render_every_workspace_page(): void
    {
        $data = $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertRenders('/admin/account');
        $this->assertRenders('/admin/business-profile');
        $this->assertRenders('/admin/projects');
        $this->assertRenders('/admin/projects/create');
        // Project binds by slug, not id.
        $this->assertRenders('/admin/projects/'.$data['project']->slug.'/edit');
        $this->assertRenders('/admin/products/create');
        $this->assertRenders('/admin/products/'.$data['product']->id.'/edit');
        $this->assertRenders('/admin/services/create');
        $this->assertRenders('/admin/services/'.$data['service']->id.'/edit');
        $this->assertRenders('/admin/ordering-delivery/'.$data['order']->id);
        $this->assertRenders('/admin/service-scheduling/'.$data['appointment']->id);
        $this->assertRenders('/admin/reports/overview');
    }

    public function test_staff_can_render_the_tabs_available_to_them(): void
    {
        $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        // Staff get operational tabs plus read-only catalogue context. Billing,
        // archive, audit, and user administration stay administrator-only.
        foreach (['overview', 'appointments', 'orders', 'services', 'products', 'feedbacks', 'messages'] as $tab) {
            $this->assertRenders('/admin?tab='.$tab);
        }

        foreach (['payment', 'archived', 'audit', 'users'] as $tab) {
            $this->get('/admin?tab='.$tab)->assertForbidden();
        }
    }

    /**
     * Staff can move an order through the operational delivery workflow, but
     * bulk actions and payment controls remain administrator-only.
     */
    public function test_staff_are_not_offered_admin_only_order_controls(): void
    {
        $this->seedWorkload();
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $html = $this->get('/admin?tab=orders')->getContent();

        $this->assertStringNotContainsString(
            'orders/bulk-status',
            $html,
            'Staff were shown the bulk status form, which their role cannot submit.'
        );
        $this->assertStringNotContainsString('name="payment_status"', $html);
        $this->assertStringNotContainsString('name="order_ids[]"', $html);
        $this->assertStringNotContainsString('name="status" disabled', $html);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $adminHtml = $this->get('/admin?tab=orders')->getContent();

        $this->assertStringContainsString('orders/bulk-status', $adminHtml);
        $this->assertStringNotContainsString('name="status" disabled', $adminHtml);
    }

    public function test_customers_are_kept_out_of_the_admin_workspace(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $this->get('/admin')->assertForbidden();
    }
}
