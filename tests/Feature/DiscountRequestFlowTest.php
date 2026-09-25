<?php

namespace Tests\Feature;

use App\Models\DiscountApplication;
use App\Models\DiscountRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\PhilippineDiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiscountRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'discounts.enabled' => true,
            'discounts.statutory_20_enabled' => true,
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
        ]);
    }

    public function test_customer_sees_enabled_discount_controls_for_an_eligible_cart(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $product = $this->eligibleProduct();

        $this->actingAs($customer)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Request Senior Citizen or PWD review.', false)
            ->assertSee('name="discount_beneficiary" value="senior"', false)
            ->assertDontSee('Senior/PWD requests are unavailable until the business tax profile and an eligible item are configured.')
            ->assertSee('id="discount-id-evidence"', false);
    }

    public function test_customer_can_request_a_ferosa_funded_discount_for_an_ordinary_cart(): void
    {
        Storage::fake('local');
        Mail::fake();
        Notification::fake();
        config([
            'discounts.enabled' => false,
            'discounts.voluntary_20_enabled' => true,
            'discounts.vat_registered' => null,
        ]);

        $customer = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'Ordinary Garden Plant',
            'price' => '100.00',
            'stock_qty' => 5,
            'category' => 'plants',
            'is_active' => true,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_NONE,
        ]);

        $this->actingAs($customer)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Request Senior Citizen or PWD review.', false)
            ->assertSee('name="discount_beneficiary" value="senior"', false)
            ->assertSee('Ferosa-funded 20% promotional discount', false);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'discount_beneficiary' => 'senior',
                'discount_id_evidence' => UploadedFile::fake()->create('senior-card.png', 64, 'image/png'),
            ])
            ->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $discountRequest = $order->discountRequest()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Ferosa-funded 20% promotion (no VAT exemption)');

        $this->actingAs($admin)
            ->post(route('admin.discount-requests.approve', $discountRequest), [
                'scheme' => PhilippineDiscountCalculator::SCHEME_VOLUNTARY_20,
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $application = DiscountApplication::query()->firstOrFail();
        $this->assertSame(PhilippineDiscountCalculator::SCHEME_VOLUNTARY_20, $application->scheme);
        $this->assertSame('0.00', $application->vat_removed);
        $this->assertSame('20.00', $application->discount_amount);
        $this->assertSame('80.00', $order->refresh()->total_amount);

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('Ferosa-funded 20% promotion')
            ->assertSee('does not claim a statutory VAT exemption.');
    }

    public function test_customer_request_keeps_original_total_until_an_admin_approves_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Notification::fake();

        [$customer, $order] = $this->submitOrderWithDiscountRequest();
        $discountRequest = $order->discountRequest()->firstOrFail();

        $this->assertSame(DiscountRequest::STATUS_PENDING, $discountRequest->status);
        $this->assertSame('senior', $discountRequest->beneficiary_type);
        $this->assertSame('112.00', $order->total_amount);
        Storage::disk('local')->assertExists($discountRequest->evidence_path);
        Storage::disk('public')->assertMissing($discountRequest->evidence_path);

        $this->actingAs($customer)
            ->get(route('orders.confirmation', $order))
            ->assertOk()
            ->assertSee('Your order total stays unchanged while Ferosa verifies your request.');
    }

    public function test_admin_can_view_private_id_and_approve_without_recording_an_id_reference(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Notification::fake();
        config([
            'discounts.enabled' => true,
            'discounts.statutory_20_enabled' => true,
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
        ]);

        [$customer, $order] = $this->submitOrderWithDiscountRequest();
        $discountRequest = $order->discountRequest()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer)
            ->get(route('admin.discount-requests.evidence', $discountRequest))
            ->assertForbidden();

        $evidenceResponse = $this->actingAs($admin)
            ->get(route('admin.discount-requests.evidence', $discountRequest))
            ->assertOk();
        $cacheControl = array_map(
            'trim',
            explode(',', (string) $evidenceResponse->headers->get('Cache-Control')),
        );
        $this->assertContains('private', $cacheControl);
        $this->assertContains('no-store', $cacheControl);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('name="id_reference_last4"', false);

        $this->actingAs($admin)
            ->post(route('admin.discount-requests.approve', $discountRequest), [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $application = DiscountApplication::query()->firstOrFail();
        $this->assertSame('12.00', $application->vat_removed);
        $this->assertSame('20.00', $application->discount_amount);
        $this->assertSame('80.00', $order->refresh()->total_amount);
        $this->assertSame(DiscountRequest::STATUS_APPROVED, $discountRequest->refresh()->status);
        $this->assertNull($application->id_reference_last4);
        $this->assertArrayNotHasKey('evidence_path', $discountRequest->toArray());
        $this->assertArrayNotHasKey('id_reference_last4', $discountRequest->toArray());
    }

    public function test_customer_cannot_submit_id_when_business_vat_status_is_unknown(): void
    {
        Storage::fake('local');
        config([
            'discounts.vat_registered' => null,
            'discounts.voluntary_20_enabled' => false,
        ]);
        $customer = User::factory()->create(['role' => 'user']);
        $product = $this->eligibleProduct();

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($customer)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Senior/PWD requests are unavailable until the business tax profile and an eligible item are configured.');

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'discount_beneficiary' => 'senior',
                'discount_id_evidence' => UploadedFile::fake()->create('senior-card.png', 64, 'image/png'),
            ])
            ->assertSessionHasErrors('discount_beneficiary');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, DiscountRequest::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('discount-id-evidence'));
    }

    public function test_customer_cannot_submit_appointment_id_when_service_rules_are_unavailable(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-09-24 09:00:00');
        config(['discounts.vat_registered' => null]);
        $customer = User::factory()->create(['role' => 'user']);
        ServiceType::query()->create([
            'name' => 'Garden Design Consultation',
            'default_fee' => 1500,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->post(route('estimator.prepare'), [
                'project_type' => 'design',
                'size' => 100,
                'tier' => 'standard',
                'addons' => [],
                'products' => [],
            ])
            ->assertRedirect(route('schedule'));

        $this->actingAs($customer)
            ->get(route('schedule'))
            ->assertOk()
            ->assertSee('Senior/PWD requests are unavailable until the business tax profile and this service are configured.');

        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'appointment_at' => '2026-09-26 09:00:00',
                'site_province_code' => '0300800000',
                'site_city_code' => '0300809000',
                'site_barangay_code' => '0300809010',
                'site_street' => '123 Mabini Street',
                'discount_beneficiary' => 'senior',
                'discount_id_evidence' => UploadedFile::fake()->create('senior-card.png', 64, 'image/png'),
            ])
            ->assertSessionHasErrors('discount_beneficiary');

        $this->assertSame(0, DiscountRequest::query()->count());
        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('discount-id-evidence'));
    }

    /** @return array{User, Order} */
    private function submitOrderWithDiscountRequest(): array
    {
        $customer = User::factory()->create(['role' => 'user']);
        $product = $this->eligibleProduct();

        $this->actingAs($customer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'discount_beneficiary' => 'senior',
                'discount_id_evidence' => UploadedFile::fake()->create('senior-card.png', 64, 'image/png'),
            ])
            ->assertRedirect();

        return [$customer, Order::query()->latest('id')->firstOrFail()];
    }

    private function eligibleProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Discount Test Plant',
            'price' => '112.00',
            'stock_qty' => 5,
            'category' => 'plants',
            'is_active' => true,
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
        ]);
    }
}
