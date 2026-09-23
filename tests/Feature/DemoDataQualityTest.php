<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_seeder_uses_realistic_references_and_payment_details(): void
    {
        $this->seed(SystemSeeder::class);

        $this->assertDatabaseHas('orders', ['order_number' => 'FRS-DEMO-001']);
        $this->assertDatabaseHas('orders', ['order_number' => 'FRS-DEMO-002']);
        $this->assertDatabaseMissing('orders', ['order_number' => 'ORD-123456']);
        $this->assertDatabaseMissing('orders', ['order_number' => 'ORD-789012']);

        $this->assertNotNull(User::query()->where('email', 'admin@cblandscaping.com')->value('phone_verified_at'));
        $this->assertNotNull(User::query()->where('email', 'user@cblandscaping.com')->value('phone_verified_at'));

        $completed = Appointment::query()->where('status', 'completed')->firstOrFail();
        $this->assertSame('paid', $completed->payment_status);
        $this->assertGreaterThan(0, (float) $completed->appointment_amount);

        $carabaoGrass = Product::query()->where('name', 'Carabao Grass')->firstOrFail();
        $this->assertTrue($carabaoGrass->supportsAreaCoverage());
        $this->assertSame('sq m', $carabaoGrass->sale_unit);
        $this->assertSame(1.0, (float) $carabaoGrass->coverage_sqm_per_unit);
        $this->assertSame(10.0, (float) $carabaoGrass->coverage_waste_percent);
    }

    public function test_demo_tidy_command_previews_then_removes_only_known_demo_clutter(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $customer = User::factory()->create(['email' => 'user@cblandscaping.com']);
        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Test message from diagnostics',
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'A useful project question',
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Photo attached',
            'attachment_path' => 'messages/missing.jpg',
            'attachment_name' => 'missing.jpg',
            'attachment_mime' => 'image/jpeg',
        ]);

        Product::query()->create([
            'name' => 'Agave',
            'description' => '25meters',
            'price' => 800,
            'stock_qty' => 3,
            'category' => 'plants',
            'is_active' => true,
        ]);

        $service = ServiceType::query()->create([
            'name' => 'Demo Visit',
            'default_fee' => 500,
            'is_active' => true,
        ]);
        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => now()->subDay(),
            'slot_key' => Appointment::slotKey($service->id, now()->subDay()),
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
            'appointment_amount' => 500,
        ]);

        $this->artisan('demo:tidy')
            ->expectsOutputToContain('Preview only')
            ->assertSuccessful();

        $this->assertSame(3, Message::query()->count());
        $this->assertSame('25meters', Product::query()->where('name', 'Agave')->value('description'));
        $this->assertSame('scheduled', $appointment->fresh()->status);

        $this->artisan('demo:tidy', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseMissing('messages', ['body' => 'Test message from diagnostics']);
        $this->assertDatabaseMissing('messages', ['attachment_path' => 'messages/missing.jpg']);
        $this->assertDatabaseHas('messages', ['body' => 'A useful project question']);
        $this->assertDatabaseHas('products', [
            'name' => 'Agave',
            'description' => 'Architectural succulent with sculptural leaves, suited to sunny, well-drained spaces.',
        ]);
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->slot_key);
    }
}
