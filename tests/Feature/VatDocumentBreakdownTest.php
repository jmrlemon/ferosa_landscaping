<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatDocumentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
            'discounts.all_checkout_products_vatable' => true,
        ]);
    }

    public function test_order_invoice_and_receipt_itemize_included_vat_without_changing_total(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-VAT-DOCUMENTS',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => '600.00',
            'items' => [['name' => 'VAT document plant', 'price' => '600.00', 'qty' => 1]],
        ]);
        $order->orderItems()->create([
            'product_id' => null,
            'name' => 'VAT document plant',
            'price' => '600.00',
            'qty' => 1,
            'discount_scheme' => 'none',
        ]);

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSeeText('Subtotal before VAT')
            ->assertSeeText('VAT included (12%)')
            ->assertSee('&#8369;535.71', false)
            ->assertSee('&#8369;64.29', false)
            ->assertSee('&#8369;600.00', false);

        $this->actingAs($customer)
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSeeText('Subtotal before VAT')
            ->assertSeeText('VAT included (12%)')
            ->assertSeeText('₱535.71')
            ->assertSeeText('₱64.29')
            ->assertSeeText('₱600.00');
    }

    public function test_appointment_documents_show_vat_only_when_service_taxability_is_confirmed(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'VAT document service',
            'description' => 'Service used for VAT document coverage.',
            'base_price' => '600.00',
            'default_fee' => '600.00',
            'discount_scheme' => 'none',
            'is_active' => true,
        ]);
        $appointmentAt = now()->addWeek()->setTime(9, 0);
        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $appointmentAt,
            'slot_key' => Appointment::slotKey($service->id, $appointmentAt),
            'status' => 'completed',
            'payment_status' => 'paid',
            'appointment_amount' => '600.00',
            'discount_scheme' => 'none',
        ]);

        $this->actingAs($customer)
            ->get(route('appointments.invoice', $appointment))
            ->assertOk()
            ->assertDontSeeText('VAT included (12%)');

        $this->actingAs($customer)
            ->get(route('appointments.receipt', $appointment))
            ->assertOk()
            ->assertDontSeeText('VAT included (12%)');

        config(['discounts.all_services_vatable' => true]);

        $this->actingAs($customer)
            ->get(route('appointments.invoice', $appointment))
            ->assertOk()
            ->assertSeeText('Subtotal before VAT')
            ->assertSeeText('VAT included (12%)')
            ->assertSee('&#8369;535.71', false)
            ->assertSee('&#8369;64.29', false)
            ->assertSee('&#8369;600.00', false);

        $this->actingAs($customer)
            ->get(route('appointments.receipt', $appointment))
            ->assertOk()
            ->assertSeeText('Subtotal before VAT')
            ->assertSeeText('VAT included (12%)')
            ->assertSeeText('PHP 535.71')
            ->assertSeeText('PHP 64.29')
            ->assertSeeText('PHP 600.00');
    }
}
