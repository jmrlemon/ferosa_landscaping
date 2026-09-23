<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_descriptions_replace_the_redundant_overall_summary(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $item = $this->item($order, 'Areca Palm', 500, 1);
        $payload = [
            'items' => [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'issue_type' => 'damaged_on_arrival',
                'issue_description' => 'The main stem was snapped.',
                'preferred_resolution' => 'replacement',
            ]],
        ];

        $this->actingAs($customer)
            ->get(route('returns.create', $order))
            ->assertOk()
            ->assertDontSeeText('Overall summary')
            ->assertDontSee('name="customer_summary"', false);

        $this->actingAs($customer)
            ->from(route('returns.create', $order))
            ->post(route('returns.store', $order), $payload)
            ->assertSessionDoesntHaveErrors('customer_summary')
            ->assertSessionHasErrors('evidence');

        $claim = app(ReturnRequestService::class)->submit($order, $customer, $payload, []);

        $this->assertSame('Areca Palm: The main stem was snapped.', $claim->customer_summary);
    }

    public function test_customer_can_submit_and_view_a_multi_item_damage_claim(): void
    {
        Storage::fake('local');
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $palm = $this->item($order, 'Areca Palm', 500, 2);
        $fern = $this->item($order, 'Boston Fern', 300, 1);

        $this->actingAs($customer)
            ->get(route('returns.create', $order))
            ->assertOk()
            ->assertSeeText('Report damaged plants')
            ->assertSeeText('Areca Palm')
            ->assertSeeText('Boston Fern');

        $response = $this->actingAs($customer)->post(route('returns.store', $order), [
            'customer_summary' => 'The box was crushed during delivery.',
            'items' => [
                [
                    'order_item_id' => $palm->id,
                    'quantity' => 1,
                    'issue_type' => 'damaged_on_arrival',
                    'issue_description' => 'The main stem was snapped.',
                    'preferred_resolution' => 'replacement',
                ],
                [
                    'order_item_id' => $fern->id,
                    'quantity' => 1,
                    'issue_type' => 'unhealthy_on_arrival',
                    'issue_description' => 'Leaves were black and wilted.',
                    'preferred_resolution' => 'refund',
                ],
            ],
            'evidence' => [
                UploadedFile::fake()->image('damage-one.jpg', 900, 700),
                UploadedFile::fake()->image('damage-two.png', 900, 700),
            ],
        ]);

        $claim = ReturnRequest::query()->with(['items', 'evidence'])->firstOrFail();
        $response->assertRedirect(route('returns.show', $claim));
        $this->assertSame('submitted', $claim->status);
        $this->assertSame(2, $claim->items->count());
        $this->assertSame(2, $claim->evidence->count());
        $this->assertStringStartsWith('RET-', $claim->claim_number);
        foreach ($claim->evidence as $evidence) {
            Storage::disk('local')->assertExists($evidence->path);
        }

        $this->actingAs($customer)
            ->get(route('returns.show', $claim))
            ->assertOk()
            ->assertSeeText($claim->claim_number)
            ->assertSeeText('Submitted')
            ->assertSeeText('Areca Palm')
            ->assertSeeText('Boston Fern');
    }

    public function test_customer_cannot_claim_another_customers_order(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($owner);

        $this->actingAs($stranger)->get(route('returns.create', $order))->assertForbidden();
        $this->actingAs($stranger)->post(route('returns.store', $order), [])->assertForbidden();
    }

    public function test_resolved_claim_keeps_its_history_but_closes_new_damage_reporting(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $item = $this->item($order, 'Resolved Palm', 500, 1);
        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-RESOLVED-HISTORY',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'resolved',
            'submitted_at' => now()->subMinutes(20),
            'resolved_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('orders'))
            ->assertOk()
            ->assertDontSeeText('Report damaged plant')
            ->assertSeeText($claim->claim_number)
            ->assertSeeText('Resolved');

        $this->actingAs($customer)
            ->get(route('returns.create', $order))
            ->assertNotFound();

        $this->actingAs($customer)
            ->post(route('returns.store', $order), $this->validPayload($item))
            ->assertSessionHasErrors('order');
        $this->assertDatabaseCount('return_requests', 1);
    }

    public function test_claim_window_closes_twenty_four_hours_after_receipt(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer, now()->subHours(24)->subSecond());
        $item = $this->item($order, 'Late Palm', 500, 1);

        $this->actingAs($customer)->get(route('returns.create', $order))->assertNotFound();
        $this->actingAs($customer)
            ->post(route('returns.store', $order), $this->validPayload($item))
            ->assertSessionHasErrors('order');
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_claim_cannot_exceed_the_unclaimed_order_quantity(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $item = $this->item($order, 'Limited Palm', 500, 2);
        $existing = ReturnRequest::query()->create([
            'claim_number' => 'RET-EXISTING',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);
        $existing->items()->create([
            'order_item_id' => $item->id,
            'quantity_claimed' => 1,
            'issue_type' => 'damaged_on_arrival',
            'issue_description' => 'Already approved damaged plant.',
            'preferred_resolution' => 'replacement',
            'resolution' => 'replacement',
            'replacement_quantity' => 1,
            'disposition' => 'not_required',
        ]);

        $payload = $this->validPayload($item);
        $payload['items'][0]['quantity'] = 2;

        $this->actingAs($customer)
            ->post(route('returns.store', $order), $payload)
            ->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('return_requests', 1);
    }

    public function test_customer_can_reply_when_information_is_needed_and_cancel_before_review(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-INFO',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'needs_information',
            'customer_summary' => 'The plant arrived damaged.',
            'decision_reason' => 'Please explain which stem was damaged.',
            'submitted_at' => now(),
        ]);

        $this->actingAs($customer)->post(route('returns.information', $claim), [
            'customer_contact_notes' => 'The tallest center stem was snapped near the soil.',
        ])->assertRedirect(route('returns.show', $claim));
        $this->assertSame('submitted', $claim->refresh()->status);
        $this->assertStringContainsString('tallest center stem', (string) $claim->customer_contact_notes);

        $this->actingAs($customer)->post(route('returns.cancel', $claim))
            ->assertRedirect(route('returns.show', $claim));
        $this->assertSame('cancelled', $claim->refresh()->status);
    }

    public function test_customer_cannot_reply_to_or_cancel_another_customers_claim(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($owner);
        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-PRIVATE',
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'status' => 'needs_information',
            'submitted_at' => now(),
        ]);

        $this->actingAs($stranger)->post(route('returns.information', $claim), [
            'customer_contact_notes' => 'Trying to change another claim.',
        ])->assertForbidden();
        $this->actingAs($stranger)->post(route('returns.cancel', $claim))->assertForbidden();
        $this->assertSame('needs_information', $claim->refresh()->status);
    }

    public function test_order_history_shows_report_action_then_tracks_the_claim(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $this->item($order, 'History Palm', 500, 1);

        $this->actingAs($customer)->get(route('orders'))
            ->assertOk()
            ->assertSeeText('Report damaged plant');

        $claim = ReturnRequest::query()->create([
            'claim_number' => 'RET-HISTORY',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($customer)->get(route('orders'))
            ->assertOk()
            ->assertSeeText('RET-HISTORY')
            ->assertSee(route('returns.show', $claim), false);
    }

    public function test_claim_rejects_non_image_and_more_than_five_evidence_files(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->deliveredOrder($customer);
        $item = $this->item($order, 'Evidence Palm', 500, 1);
        $payload = $this->validPayload($item);
        $payload['evidence'] = [UploadedFile::fake()->create('not-a-photo.pdf', 50, 'application/pdf')];

        $this->actingAs($customer)->post(route('returns.store', $order), $payload)
            ->assertSessionHasErrors('evidence.0');
        $this->assertDatabaseCount('return_requests', 0);

        $payload['evidence'] = collect(range(1, 6))
            ->map(fn (int $number) => UploadedFile::fake()->image("damage-{$number}.jpg"))
            ->all();
        $this->actingAs($customer)->post(route('returns.store', $order), $payload)
            ->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('return_requests', 0);
    }

    /** @return array<string, mixed> */
    private function validPayload(OrderItem $item): array
    {
        return [
            'customer_summary' => 'Damage found while opening the delivery.',
            'items' => [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'issue_type' => 'damaged_on_arrival',
                'issue_description' => 'Stem arrived broken.',
                'preferred_resolution' => 'replacement',
            ]],
            'evidence' => [UploadedFile::fake()->image('damage.jpg', 900, 700)],
        ];
    }

    private function deliveredOrder(User $customer, mixed $receivedAt = null): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-'.fake()->unique()->numerify('######'),
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 1300,
            'delivery_method' => 'delivery',
            'delivered_at' => $receivedAt ?? now()->subHour(),
            'customer_confirmed_at' => $receivedAt ?? now()->subHour(),
        ]);
    }

    private function item(Order $order, string $name, float $price, int $quantity): OrderItem
    {
        return $order->orderItems()->create([
            'name' => $name,
            'price' => $price,
            'qty' => $quantity,
        ]);
    }
}
