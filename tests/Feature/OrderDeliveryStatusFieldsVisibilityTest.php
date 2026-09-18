<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderDeliveryStatusFieldsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_detail_fields_follow_the_selected_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);

        $expectedStates = [
            'confirmed' => [
                'out_for_delivery' => false,
                'delivered' => false,
            ],
            'out_for_delivery' => [
                'out_for_delivery' => true,
                'delivered' => false,
            ],
            'delivered' => [
                'out_for_delivery' => false,
                'delivered' => true,
            ],
        ];

        foreach ($expectedStates as $orderStatus => $panelStates) {
            $order = Order::query()->create([
                'user_id' => $customer->id,
                'order_number' => 'FRS-VISIBILITY-'.strtoupper($orderStatus),
                'status' => $orderStatus,
                'payment_status' => 'paid',
                'delivery_method' => 'delivery',
                'total_amount' => 500,
            ]);

            $response = $this->actingAs($admin)
                ->get(route('admin.orders.show', $order))
                ->assertOk()
                ->assertSee('data-order-status-select', false)
                ->assertSee('aria-controls="order-status-fields-out-for-delivery order-status-fields-delivered"', false);

            foreach ($panelStates as $panelStatus => $isActive) {
                $this->assertPanelState($response, $panelStatus, $isActive);
            }
        }
    }

    public function test_unpaid_order_disables_delivered_until_payment_is_selected_as_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-DELIVERY-PAYMENT-GATE',
            'status' => 'out_for_delivery',
            'payment_status' => 'unpaid',
            'delivery_method' => 'delivery',
            'total_amount' => 500,
            'driver_name' => 'Juan Rider',
            'driver_phone' => '09171234567',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Payment must be marked Paid before this order can be delivered')
            ->assertSee('data-payment-status-select', false);

        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        $xpath = new DOMXPath($document);
        $deliveredOption = $xpath->query('//select[@id="order-status-select"]/option[@value="delivered"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $deliveredOption);
        $this->assertTrue($deliveredOption->hasAttribute('disabled'));
        $this->assertTrue($deliveredOption->hasAttribute('data-requires-paid'));
    }

    private function assertPanelState(TestResponse $response, string $panelStatus, bool $isActive): void
    {
        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        $xpath = new DOMXPath($document);
        $panel = $xpath->query(sprintf('//*[@data-order-status-fields="%s"]', $panelStatus))->item(0);

        $this->assertInstanceOf(DOMElement::class, $panel, "Missing {$panelStatus} workflow panel.");
        $this->assertSame($isActive ? 'false' : 'true', $panel->getAttribute('aria-hidden'));
        $this->assertSame(! $isActive, $panel->hasAttribute('hidden'));

        $controls = $xpath->query('.//input | .//textarea | .//select', $panel);
        $this->assertNotFalse($controls);
        $this->assertGreaterThan(0, $controls->length);

        foreach ($controls as $control) {
            $this->assertInstanceOf(DOMElement::class, $control);
            $this->assertSame(! $isActive, $control->hasAttribute('disabled'));
        }
    }
}
