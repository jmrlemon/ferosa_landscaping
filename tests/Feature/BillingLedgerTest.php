<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_leaves_a_balance_and_full_payment_settles_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(1250.00);

        $this->actingAs($admin)
            ->post(route('admin.orders.payments.store', $order), [
                'amount' => 500,
                'method' => 'gcash',
                'reference' => '8817263',
            ])->assertRedirect();

        $order->refresh();
        $this->assertSame('partial', $order->payment_status);
        $this->assertSame(750.00, $order->balanceDue());
        $this->assertSame(500.00, $order->totalPaid());

        $this->actingAs($admin)
            ->post(route('admin.orders.payments.store', $order), [
                'amount' => 750,
                'method' => 'cash',
            ])->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(0.0, $order->balanceDue());
        $this->assertNotNull($order->payment_verified_at);
    }

    public function test_a_payment_cannot_exceed_the_outstanding_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(500.00);

        $this->actingAs($admin)
            ->post(route('admin.orders.payments.store', $order), [
                'amount' => 500.01,
                'method' => 'cash',
            ])->assertSessionHasErrors('amount');

        $this->assertSame(0, $order->payments()->count());
        $this->assertSame('unpaid', $order->refresh()->payment_status);
    }

    public function test_voiding_a_payment_restores_the_balance_but_keeps_the_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(400.00);

        app(BillingService::class)->record($order, [
            'amount' => 400,
            'method' => 'cash',
            'recorded_by' => $admin->id,
        ]);

        $this->assertSame('paid', $order->refresh()->payment_status);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.payments.void', $payment), ['void_reason' => 'Recorded against the wrong order.'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(400.00, $order->balanceDue());

        // The ledger is append-only: the row survives, it just stops counting.
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(0, $order->activePayments()->count());
        $this->assertNotNull($payment->refresh()->voided_at);
    }

    public function test_void_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(400.00);

        $payment = app(BillingService::class)->record($order, ['amount' => 400, 'method' => 'cash']);

        $this->actingAs($admin)
            ->put(route('admin.payments.void', $payment), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->assertNull($payment->refresh()->voided_at);
        $this->assertSame('paid', $order->refresh()->payment_status);
    }

    public function test_marking_an_order_paid_writes_a_settling_ledger_entry(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(300.00);

        $this->actingAs($admin)
            ->put(route('admin.orders.status', $order), [
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ])->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        // The status and the ledger must never disagree.
        $this->assertSame(300.00, $order->totalPaid());
        $this->assertSame(1, $order->activePayments()->count());
    }

    public function test_appointments_share_the_same_ledger(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment(1500.00);

        $this->actingAs($admin)
            ->post(route('admin.appointments.payments.store', $appointment), [
                'amount' => 600,
                'method' => 'cash',
            ])->assertRedirect();

        $appointment->refresh();
        $this->assertSame('partial', $appointment->payment_status);
        $this->assertSame(900.00, $appointment->balanceDue());
        $this->assertSame('INV-S'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT), $appointment->invoiceNumber());
    }

    public function test_customers_can_read_their_own_invoice_but_not_another_customers(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->order(750.00, $owner);

        app(BillingService::class)->record($order, ['amount' => 250, 'method' => 'cash']);

        $this->actingAs($owner)->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee($order->invoiceNumber())
            ->assertSee('500.00'); // balance due

        $this->actingAs($stranger)->get(route('orders.invoice', $order))->assertForbidden();
        $this->actingAs($staff)->get(route('orders.invoice', $order))->assertOk();
    }

    public function test_only_admins_can_record_payments(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->order(400.00);

        $this->actingAs($staff)
            ->post(route('admin.orders.payments.store', $order), ['amount' => 100, 'method' => 'cash'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.orders.payments.store', $order), ['amount' => 100, 'method' => 'cash'])
            ->assertForbidden();

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_a_refunded_order_is_not_overwritten_by_the_ledger(): void
    {
        $order = $this->order(400.00);
        $order->forceFill(['payment_status' => 'refunded'])->save();

        app(BillingService::class)->record($order, ['amount' => 400, 'method' => 'cash']);

        // Refunded is a manual decision about money already returned; the ledger
        // sum must not flip it back to paid.
        $this->assertSame('refunded', $order->refresh()->payment_status);
    }

    private function order(float $total, ?User $customer = null): Order
    {
        $customer ??= User::factory()->create(['role' => 'user']);

        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-TEST-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => $total,
            'items' => [['name' => 'Garden Soil', 'price' => $total, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ]);
    }

    private function appointment(float $amount): Appointment
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'Garden Maintenance',
            'description' => 'Test service',
            'base_price' => $amount,
            'is_active' => true,
        ]);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => now()->addWeek(),
            'slot_key' => Appointment::slotKey($service->id, now()->addWeek()),
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
            'appointment_amount' => $amount,
        ]);
    }
}
