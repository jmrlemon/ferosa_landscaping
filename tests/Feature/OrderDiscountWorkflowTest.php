<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DiscountApplication;
use App\Models\Order;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PhilippineDiscountCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderDiscountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'discounts.enabled' => true,
            'discounts.statutory_20_enabled' => true,
            'discounts.bnpc_5_enabled' => true,
            'discounts.bnpc_5_workflow_complete' => true,
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
            'discounts.bnpc_weekly_limit' => '2500.00',
        ]);
    }

    public function test_admin_can_apply_statutory_discount_without_recording_an_id_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->order($customer, '1120.00', PhilippineDiscountCalculator::SCHEME_STATUTORY_20);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('name="id_reference_last4"', false);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'senior',
                'id_reference_last4' => '1234',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $discount = DiscountApplication::query()->firstOrFail();
        $this->assertSame('approved', $discount->status);
        $this->assertSame('120.00', $discount->vat_removed);
        $this->assertSame('200.00', $discount->discount_amount);
        $this->assertSame('800.00', $discount->net_total);
        $this->assertNull($discount->id_reference_last4);
        $this->assertArrayNotHasKey('id_reference_last4', $discount->toArray());
        $audit = AuditLog::query()->where('action', 'order.discount.apply')->firstOrFail();
        $this->assertStringNotContainsString('id_reference_last4', json_encode($audit->after, JSON_THROW_ON_ERROR));
        $this->assertSame('800.00', $order->refresh()->total_amount);

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('Less: VAT exemption')
            ->assertSee('Senior Citizen 20% discount')
            ->assertSee('800.00');
    }

    public function test_non_admin_cannot_apply_a_discount(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($staff)
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'senior',
                'eligibility_confirmed' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(0, DiscountApplication::query()->count());
        $this->assertSame('1120.00', $order->refresh()->total_amount);
    }

    public function test_discount_cannot_be_applied_after_a_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );
        app(BillingService::class)->record($order, ['amount' => 100, 'method' => 'cash']);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'pwd',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, DiscountApplication::query()->count());
        $this->assertSame('1120.00', $order->refresh()->total_amount);
    }

    public function test_discount_cannot_change_an_order_with_a_payment_proof_under_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );
        $order->forceFill(['payment_status' => 'pending_verification'])->save();

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'senior',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, DiscountApplication::query()->count());
        $this->assertSame('1120.00', $order->refresh()->total_amount);
    }

    public function test_voiding_an_unpaid_discount_restores_the_original_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'eligibility_confirmed' => '1',
        ]);

        $discount = DiscountApplication::query()->firstOrFail();

        $this->actingAs($admin)
            ->delete('/admin/orders/'.$order->id.'/discounts/'.$discount->id, [
                'void_reason' => 'Customer chose not to use the benefit.',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('voided', $discount->refresh()->status);
        $this->assertSame('1120.00', $order->refresh()->total_amount);
        $this->assertNull($order->activeDiscount()->first());
    }

    public function test_discount_cannot_be_voided_after_the_order_is_confirmed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'eligibility_confirmed' => '1',
        ]);

        $discount = DiscountApplication::query()->firstOrFail();
        $order->forceFill(['status' => 'confirmed'])->save();

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->delete('/admin/orders/'.$order->id.'/discounts/'.$discount->id, [
                'void_reason' => 'Attempted after confirmation.',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame('approved', $discount->refresh()->status);
        $this->assertSame('800.00', $order->refresh()->total_amount);
    }

    public function test_mixed_basket_invoice_does_not_claim_every_line_is_vat_exempt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->order($customer, '1620.00', PhilippineDiscountCalculator::SCHEME_STATUTORY_20);
        $order->orderItems()->firstOrFail()->forceFill(['price' => '1120.00'])->save();
        $order->orderItems()->create([
            'product_id' => null,
            'name' => 'Ordinary item',
            'price' => '500.00',
            'qty' => 1,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE,
        ]);
        $order->forceFill(['items' => [
            [
                'name' => 'Eligible item',
                'price' => '1120.00',
                'qty' => 1,
                'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            ],
            [
                'name' => 'Ordinary item',
                'price' => '500.00',
                'qty' => 1,
                'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE,
            ],
        ]])->save();

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'eligibility_confirmed' => '1',
        ])->assertRedirect();

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('VAT exemption applies only to eligible items.')
            ->assertSee('Ordinary item')
            ->assertDontSee('VAT charged');
    }

    public function test_discount_cannot_be_voided_after_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'eligibility_confirmed' => '1',
        ]);
        $discount = DiscountApplication::query()->firstOrFail();
        app(BillingService::class)->record($order->refresh(), ['amount' => 100, 'method' => 'cash']);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->delete('/admin/orders/'.$order->id.'/discounts/'.$discount->id, [
                'void_reason' => 'Attempted after payment.',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame('approved', $discount->refresh()->status);
        $this->assertSame('800.00', $order->refresh()->total_amount);
    }

    public function test_bnpc_weekly_cap_is_shared_across_a_customers_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $firstOrder = $this->order($customer, '2000.00', PhilippineDiscountCalculator::SCHEME_BNPC_5);
        $secondOrder = $this->order($customer, '1000.00', PhilippineDiscountCalculator::SCHEME_BNPC_5);

        foreach ([$firstOrder, $secondOrder] as $order) {
            $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5,
                'beneficiary_type' => 'pwd',
                'eligibility_confirmed' => '1',
            ])->assertRedirect(route('admin.orders.show', $order));
        }

        $this->assertSame('100.00', $firstOrder->activeDiscount()->firstOrFail()->discount_amount);
        $this->assertSame('500.00', $secondOrder->activeDiscount()->firstOrFail()->discount_base);
        $this->assertSame('25.00', $secondOrder->activeDiscount()->firstOrFail()->discount_amount);
        $this->assertSame('975.00', $secondOrder->refresh()->total_amount);
    }

    public function test_bnpc_stays_unavailable_until_current_booklet_and_eligibility_checks_are_supported(): void
    {
        config(['discounts.bnpc_5_workflow_complete' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1000.00',
            PhilippineDiscountCalculator::SCHEME_BNPC_5,
        );

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5,
                'beneficiary_type' => 'pwd',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, DiscountApplication::query()->count());
        $this->assertSame('1000.00', $order->refresh()->total_amount);
    }

    public function test_bnpc_weekly_cap_uses_purchase_week_when_approval_is_delayed(): void
    {
        $purchaseAt = CarbonImmutable::parse('2026-09-20 08:00:00', 'Asia/Manila');
        $approvalAt = CarbonImmutable::parse('2026-09-21 00:30:00', 'Asia/Manila');
        Carbon::setTestNow($purchaseAt);
        CarbonImmutable::setTestNow($purchaseAt);

        try {
            $admin = User::factory()->create(['role' => 'admin']);
            $customer = User::factory()->create(['role' => 'user']);
            $firstOrder = $this->order($customer, '2000.00', PhilippineDiscountCalculator::SCHEME_BNPC_5);
            $secondOrder = $this->order($customer, '1000.00', PhilippineDiscountCalculator::SCHEME_BNPC_5);

            foreach ([$firstOrder, $secondOrder] as $order) {
                $order->forceFill([
                    'created_at' => $purchaseAt,
                    'updated_at' => $purchaseAt,
                ])->saveQuietly();
            }

            $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5,
                'beneficiary_type' => 'pwd',
                'eligibility_confirmed' => '1',
            ])->assertRedirect(route('admin.orders.show', $firstOrder));

            $firstOrder->activeDiscount()->firstOrFail()->forceFill([
                'verified_at' => $purchaseAt,
            ])->saveQuietly();

            Carbon::setTestNow($approvalAt);
            CarbonImmutable::setTestNow($approvalAt);
            $this->actingAs($admin)->post('/admin/orders/'.$secondOrder->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_BNPC_5,
                'beneficiary_type' => 'pwd',
                'eligibility_confirmed' => '1',
            ])->assertRedirect(route('admin.orders.show', $secondOrder));

            $secondDiscount = $secondOrder->activeDiscount()->firstOrFail();
            $this->assertSame('500.00', $secondDiscount->discount_base);
            $this->assertSame('25.00', $secondDiscount->discount_amount);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_billing_panel_exposes_the_guarded_discount_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Apply Senior/PWD discount')
            ->assertSee('VAT is removed before the 20% discount');

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/discounts', [
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'eligibility_confirmed' => '1',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Discount and VAT adjustment')
            ->assertDontSee('Void discount');
    }

    public function test_feature_is_fail_closed_when_compliance_switch_is_disabled(): void
    {
        config([
            'discounts.enabled' => false,
            'discounts.voluntary_20_enabled' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Discount tools are disabled pending compliance approval.');

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'senior',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, DiscountApplication::query()->count());
    }

    public function test_statutory_discount_is_blocked_until_vat_registration_is_confirmed(): void
    {
        config(['discounts.vat_registered' => null]);

        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(
            User::factory()->create(['role' => 'user']),
            '1120.00',
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        );

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post('/admin/orders/'.$order->id.'/discounts', [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'beneficiary_type' => 'senior',
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, DiscountApplication::query()->count());
        $this->assertSame('1120.00', $order->refresh()->total_amount);
    }

    private function order(User $customer, string $price, string $discountScheme): Order
    {
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-DISCOUNT-'.strtoupper(fake()->unique()->bothify('????##')),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => $price,
            'items' => [[
                'product_id' => null,
                'name' => 'Eligible item',
                'price' => $price,
                'qty' => 1,
                'discount_scheme' => $discountScheme,
            ]],
            'delivery_method' => 'pickup',
            'payment_method' => 'cod',
        ]);

        $order->orderItems()->create([
            'product_id' => null,
            'name' => 'Eligible item',
            'price' => $price,
            'qty' => 1,
            'discount_scheme' => $discountScheme,
        ]);

        return $order;
    }
}
