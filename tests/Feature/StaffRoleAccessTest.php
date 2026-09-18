<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function service(): ServiceType
    {
        return ServiceType::query()->create([
            'name' => 'Garden Maintenance',
            'default_fee' => 1500,
            'is_active' => true,
        ]);
    }

    private function appointmentFor(User $customer, string $status = 'scheduled'): Appointment
    {
        $service = $this->service();
        $at = Carbon::now()->addDays(4)->setTime(9, 0);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $at,
            'slot_key' => in_array($status, ['scheduled', 'confirmed'], true)
                ? Appointment::slotKey($service->id, $at)
                : null,
            'status' => $status,
            'payment_status' => 'unpaid',
            'appointment_amount' => 1500,
        ]);
    }

    private function orderFor(User $customer, string $status = 'pending'): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-STAFF-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => $status,
            'payment_status' => 'unpaid',
            'total_amount' => 500,
            'items' => [],
            'delivery_method' => 'delivery',
            'payment_method' => 'cod',
        ]);
    }

    public function test_staff_can_complete_appointments_without_touching_payment_state(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $appointment = $this->appointmentFor($this->customer());

        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), ['status' => 'completed'])
            ->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertSame('unpaid', $appointment->payment_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_can_cancel_an_appointment_without_creating_a_financial_entry(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $appointment = $this->appointmentFor($this->customer());

        $this->actingAs($staff)
            ->put(route('admin.appointments.cancel', $appointment))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'appointments']));

        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertNull($appointment->slot_key);
        $this->assertSame('Cancelled by staff.', $appointment->cancel_reason);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_can_run_delivery_workflow_and_upload_proof(): void
    {
        Storage::fake('public');
        Notification::fake();
        $staff = $this->staff();
        $order = $this->orderFor($this->customer());

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'confirmed',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'out_for_delivery',
                'driver_name' => 'Juan Rider',
                'driver_phone' => '09171234567',
                'dispatch_notes' => 'Call on arrival.',
            ])
            ->assertSessionHasNoErrors();

        app(BillingService::class)->settle($order->refresh(), null, 'cash');

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'delivered',
                'driver_name' => 'Juan Rider',
                'driver_phone' => '09171234567',
                'delivery_recipient_name' => 'Maria Santos',
                'delivery_proof' => UploadedFile::fake()->create('delivery-proof.png', 10, 'image/png'),
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('Maria Santos', $order->delivery_recipient_name);
        $this->assertNotNull($order->delivery_proof_url);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_unpaid_order_cannot_be_marked_delivered_by_admin_or_staff(): void
    {
        Storage::fake('public');
        Notification::fake();
        $customer = $this->customer();

        foreach (['admin', 'staff'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $order = $this->orderFor($customer, 'out_for_delivery');
            $order->forceFill([
                'driver_name' => 'Juan Rider',
                'driver_phone' => '09171234567',
                'dispatched_at' => now(),
            ])->save();

            $payload = [
                'status' => 'delivered',
                'driver_name' => 'Juan Rider',
                'driver_phone' => '09171234567',
                'delivery_recipient_name' => 'Maria Santos',
                'delivery_proof' => UploadedFile::fake()->create("{$role}-delivery-proof.png", 10, 'image/png'),
            ];

            if ($role === 'admin') {
                $payload['payment_status'] = 'unpaid';
            }

            $this->actingAs($actor)
                ->put(route('admin.orders.status', $order), $payload)
                ->assertSessionHasErrors('payment_status');

            $order->refresh();
            $this->assertSame('out_for_delivery', $order->status);
            $this->assertSame('unpaid', $order->payment_status);
            $this->assertNull($order->delivery_proof_url);
            $this->assertNull($order->delivered_at);
        }
    }

    public function test_admin_can_mark_cod_order_paid_and_delivered_together(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->orderFor($this->customer(), 'out_for_delivery');
        $order->forceFill([
            'driver_name' => 'Juan Rider',
            'driver_phone' => '09171234567',
            'dispatched_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.orders.status', $order), [
                'status' => 'delivered',
                'payment_status' => 'paid',
                'driver_name' => 'Juan Rider',
                'driver_phone' => '09171234567',
                'delivery_recipient_name' => 'Maria Santos',
                'delivery_proof' => UploadedFile::fake()->create('paid-delivery-proof.png', 10, 'image/png'),
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(500.0, (float) $order->activePayments()->sum('amount'));
        $this->assertNotNull($order->delivered_at);
    }

    public function test_forged_staff_payment_fields_are_rejected_on_operational_endpoints(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $order = $this->orderFor($customer);
        $appointment = $this->appointmentFor($customer);

        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), [
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ])
            ->assertSessionHasErrors('payment_status');

        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ])
            ->assertSessionHasErrors('payment_status');

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame('scheduled', $appointment->refresh()->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('unpaid', $appointment->payment_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_cannot_reach_privileged_workspace_actions(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 10,
            'category' => 'Materials',
            'is_active' => true,
        ]);
        $service = $this->service();
        $appointment = $this->appointmentFor($customer);
        $order = $this->orderFor($customer);
        $payment = Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'amount' => 100,
            'method' => 'cash',
            'paid_at' => now(),
        ]);

        $forbidden = [
            'archived tab' => fn () => $this->get(route('admin.dashboard', ['tab' => 'archived'])),
            'audit tab' => fn () => $this->get(route('admin.dashboard', ['tab' => 'audit'])),
            'users tab' => fn () => $this->get(route('admin.dashboard', ['tab' => 'users'])),
            'payment tab' => fn () => $this->get(route('admin.dashboard', ['tab' => 'payment'])),
            'overview report' => fn () => $this->get(route('admin.reports.overview')),
            'overview export' => fn () => $this->get(route('admin.reports.overview-csv')),
            'product create' => fn () => $this->get(route('admin.products.create')),
            'product store' => fn () => $this->post(route('admin.products.store')),
            'product edit' => fn () => $this->get(route('admin.products.edit', $product)),
            'product update' => fn () => $this->put(route('admin.products.update', $product)),
            'product delete' => fn () => $this->delete(route('admin.products.delete', $product)),
            'product restore' => fn () => $this->put(route('admin.products.restore', $product)),
            'product AR upload' => fn () => $this->post(route('admin.ar-models.upload', $product)),
            'product AR delete' => fn () => $this->delete(route('admin.ar-models.delete', $product)),
            'service create' => fn () => $this->get(route('admin.services.create')),
            'service store' => fn () => $this->post(route('admin.services.store')),
            'service edit' => fn () => $this->get(route('admin.services.edit', $service)),
            'service update' => fn () => $this->put(route('admin.services.update', $service)),
            'service delete' => fn () => $this->delete(route('admin.services.delete', $service)),
            'service restore' => fn () => $this->put(route('admin.services.restore', $service)),
            'inventory list' => fn () => $this->get(route('admin.inventory.index')),
            'inventory item' => fn () => $this->get(route('admin.inventory.show', $product)),
            'inventory restock' => fn () => $this->post(route('admin.inventory.restock', $product)),
            'inventory adjust' => fn () => $this->post(route('admin.inventory.adjust', $product)),
            'payment settings' => fn () => $this->put(route('admin.payment-settings.update')),
            'business profile' => fn () => $this->get(route('admin.business-profile.edit')),
            'business profile update' => fn () => $this->put(route('admin.business-profile.update')),
            'user role' => fn () => $this->put(route('admin.users.role', $customer), ['role' => 'admin']),
            'appointment scope' => fn () => $this->put(route('admin.appointments.scope', $appointment)),
            'appointment archive' => fn () => $this->put(route('admin.appointments.archive', $appointment)),
            'appointment restore' => fn () => $this->put(route('admin.appointments.restore', $appointment)),
            'appointment payment' => fn () => $this->post(route('admin.appointments.payments.store', $appointment)),
            'order archive' => fn () => $this->put(route('admin.orders.archive', $order)),
            'order restore' => fn () => $this->put(route('admin.orders.restore', $order)),
            'bulk order status' => fn () => $this->post(route('admin.orders.bulk-status')),
            'order payment' => fn () => $this->post(route('admin.orders.payments.store', $order)),
            'void payment' => fn () => $this->put(route('admin.payments.void', $payment)),
        ];

        $this->actingAs($staff);
        foreach ($forbidden as $label => $request) {
            $response = $request();
            $this->assertSame(403, $response->status(), $label.' should be forbidden (redirect: '.($response->headers->get('Location') ?? 'none').')');
        }
    }

    public function test_staff_workspace_hides_admin_only_links_and_payment_inputs(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $appointment = $this->appointmentFor($customer);
        $order = $this->orderFor($customer);

        $this->actingAs($staff)
            ->get(route('admin.dashboard', ['tab' => 'appointments']))
            ->assertOk()
            ->assertDontSee(route('admin.services.create'), false)
            ->assertDontSee(route('admin.products.create'), false)
            ->assertDontSee(route('admin.orders.bulk-status'), false)
            ->assertDontSee('tab=archived', false)
            ->assertDontSee('tab=audit', false)
            ->assertDontSee('tab=users', false);

        $this->actingAs($staff)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee('name="payment_status"', false);

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('name="payment_status"', false);
    }

    public function test_staff_cannot_open_or_mutate_archived_operational_records(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $appointment = $this->appointmentFor($customer);
        $appointment->update(['archived_at' => now()]);
        $order = $this->orderFor($customer);
        $order->update(['archived_at' => now()]);

        $this->actingAs($staff)
            ->get(route('admin.appointments.show', $appointment))
            ->assertNotFound();
        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertNotFound();
        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertNotFound();
        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), ['status' => 'confirmed'])
            ->assertNotFound();
    }

    public function test_staff_detail_does_not_expose_audit_history(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = $this->staff();
        $appointment = $this->appointmentFor($this->customer());

        $this->actingAs($admin)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertDontSeeText('Activity History');

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertSeeText('Activity History');
    }
}
