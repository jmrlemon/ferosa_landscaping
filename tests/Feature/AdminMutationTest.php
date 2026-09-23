<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\AppointmentStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The admin workspace holds the actions with the most authority behind them -
 * granting a role, verifying payments, cancelling an order, and archiving - and
 * was also the least covered part of the system. These are the guards that
 * matter: the role gate, the state machine, the audit trail, and the stock
 * ledger staying honest when an order is cancelled in bulk.
 */
class AdminMutationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

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
            'slot_key' => Appointment::slotKey($service->id, $at),
            'appointment_amount' => 1500,
            'status' => $status,
        ]);
    }

    private function orderFor(User $customer, string $status = 'pending', ?Product $product = null, int $qty = 2): Order
    {
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => $status,
            'payment_status' => 'unpaid',
            'total_amount' => 500,
            'items' => [['name' => 'Garden Soil', 'price' => 250, 'qty' => $qty]],
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ]);

        if ($product) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'qty' => $qty,
            ]);
        }

        return $order;
    }

    // -- Role changes --------------------------------------------------------

    public function test_a_role_change_is_recorded_in_the_audit_log(): void
    {
        $admin = $this->admin();
        $target = $this->customer();

        $this->actingAs($admin)
            ->put(route('admin.users.role', $target), ['role' => 'staff'])
            ->assertRedirect(route('admin.dashboard', ['tab' => 'users']));

        $this->assertSame('staff', $target->refresh()->role);

        // Handing over authority is the one action that must never be silent.
        $entry = AuditLog::query()->where('action', 'user.role.update')->firstOrFail();
        $this->assertSame(User::class, $entry->auditable_type);
        $this->assertSame($target->id, (int) $entry->auditable_id);
    }

    public function test_an_admin_cannot_change_their_own_role_and_staff_cannot_change_anyones(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        $target = $this->customer();

        // Self-demotion would let the last admin lock everyone out.
        $this->actingAs($admin)
            ->put(route('admin.users.role', $admin), ['role' => 'user']);
        $this->assertSame('admin', $admin->refresh()->role);

        // Roles are admin-only, not staff.
        $this->actingAs($staff)
            ->put(route('admin.users.role', $target), ['role' => 'admin'])
            ->assertForbidden();
        $this->assertSame('user', $target->refresh()->role);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.role.update']);
    }

    public function test_a_customer_cannot_reach_the_admin_workspace_at_all(): void
    {
        $customer = $this->customer();
        $appointment = $this->appointmentFor($customer);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.appointments.show', $appointment))->assertForbidden();
        $this->actingAs($customer)
            ->put(route('admin.appointments.status', $appointment), ['status' => 'confirmed', 'payment_status' => 'unpaid'])
            ->assertForbidden();
    }

    // -- Appointment status --------------------------------------------------

    public function test_staff_confirming_a_visit_audits_it_and_tells_the_customer(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $customer = $this->customer();
        $appointment = $this->appointmentFor($customer);

        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'confirmed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $appointment->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.status.update']);
        Notification::assertSentTo($customer, AppointmentStatusChanged::class);
    }

    public function test_an_appointment_cannot_skip_the_state_machine(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $appointment = $this->appointmentFor($this->customer());

        // scheduled -> completed is not a transition the model allows.
        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('scheduled', $appointment->refresh()->status);

        // A cancelled visit is history; nothing moves it back out.
        $cancelled = $this->appointmentFor($this->customer(), 'cancelled');
        $this->actingAs($staff)
            ->put(route('admin.appointments.status', $cancelled), [
                'status' => 'confirmed',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $cancelled->refresh()->status);
    }

    public function test_a_confirmed_appointment_cannot_move_back_to_scheduled(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $appointment = $this->appointmentFor($this->customer(), 'confirmed');

        $this->actingAs($admin)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'scheduled',
                'payment_status' => 'unpaid',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('confirmed', $appointment->refresh()->status);
    }

    public function test_an_unpaid_appointment_cannot_be_completed(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $appointment = $this->appointmentFor($this->customer(), 'confirmed');

        $this->actingAs($admin)
            ->put(route('admin.appointments.status', $appointment), [
                'status' => 'completed',
                'payment_status' => 'unpaid',
            ])
            ->assertSessionHasErrors('payment_status');

        $this->assertSame('confirmed', $appointment->refresh()->status);
        $this->assertSame('unpaid', $appointment->payment_status);
    }

    public function test_archiving_a_visit_hides_it_without_destroying_it_and_restore_brings_it_back(): void
    {
        $admin = $this->admin();
        $appointment = $this->appointmentFor($this->customer());

        $this->actingAs($admin)
            ->put(route('admin.appointments.archive', $appointment))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'appointments']));
        $this->assertNotNull($appointment->refresh()->archived_at);

        $this->actingAs($admin)
            ->put(route('admin.appointments.restore', $appointment));
        $this->assertNull($appointment->refresh()->archived_at);

        // The row itself is never removed - the audit trail would lose its target.
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.archive']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.restore']);
    }

    // -- Bulk order status ---------------------------------------------------

    public function test_bulk_confirm_moves_only_the_orders_that_may_move(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $pending = $this->orderFor($customer, 'pending');
        $alreadyDelivered = $this->orderFor($customer, 'delivered');

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk-status'), [
                'order_ids' => [$pending->id, $alreadyDelivered->id],
                'status' => 'confirmed',
            ]);

        $this->assertSame('confirmed', $pending->refresh()->status);

        // delivered -> confirmed is not a transition; it is skipped, not forced.
        $this->assertSame('delivered', $alreadyDelivered->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'order.status.bulk-update')->count());
    }

    public function test_dispatch_and_delivery_cannot_be_applied_in_bulk(): void
    {
        $admin = $this->admin();
        $order = $this->orderFor($this->customer(), 'confirmed');

        // These need proof of dispatch and a recipient, one order at a time.
        foreach (['out_for_delivery', 'delivered', 'completed'] as $status) {
            $this->actingAs($admin)
                ->post(route('admin.orders.bulk-status'), [
                    'order_ids' => [$order->id],
                    'status' => $status,
                ])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame('confirmed', $order->refresh()->status);
    }

    public function test_a_bulk_cancellation_returns_the_stock_it_took(): void
    {
        $admin = $this->admin();
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 8,
            'category' => 'Materials',
            'is_active' => true,
        ]);
        $order = $this->orderFor($this->customer(), 'pending', $product, 3);

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk-status'), [
                'order_ids' => [$order->id],
                'status' => 'cancelled',
            ]);

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(11, (int) $product->refresh()->stock_qty);
    }

    // -- Payment settings ----------------------------------------------------

    public function test_gcash_details_are_admin_only(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->put(route('admin.payment-settings.update'), [
                'gcash_name' => 'Not Allowed',
                'gcash_number' => '09170000000',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.payment-settings.update'), [
                'gcash_name' => 'Ferosa Landscaping',
                'gcash_number' => '09171234567',
            ]);

        $this->assertSame('Ferosa Landscaping', AppSetting::getValue('gcash_name'));
        $this->assertSame('09171234567', AppSetting::getValue('gcash_number'));
    }
}
