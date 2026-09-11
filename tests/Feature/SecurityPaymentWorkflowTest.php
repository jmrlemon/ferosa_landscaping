<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPaymentReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_gcash_checkout_requires_private_proof_and_rejects_duplicate_reference(): void
    {
        Storage::fake('local');
        Mail::fake();
        Notification::fake();
        AppSetting::setValue('gcash_number', '09171234567');

        $firstCustomer = User::factory()->create(['role' => 'user']);
        $secondCustomer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(stock: 4);

        $this->actingAs($firstCustomer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($firstCustomer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'gcash',
            'payment_reference' => '1234-5678 90',
            'payment_proof' => $this->fakePng('first-receipt.png'),
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('pending_verification', $order->payment_status);
        $this->assertSame('1234567890', $order->payment_reference_normalized);
        $this->assertNotNull($order->payment_proof_path);
        Storage::disk('local')->assertExists($order->payment_proof_path);

        $this->actingAs($firstCustomer)
            ->get(route('orders.payment-proof', $order))
            ->assertOk();
        $this->actingAs($secondCustomer)
            ->get(route('orders.payment-proof', $order))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('orders.payment-proof', $order))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Customer Payment Receipt')
            ->assertSeeText('Pending verification');

        $this->actingAs($secondCustomer)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($secondCustomer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890',
            'payment_proof' => $this->fakePng('duplicate-receipt.png'),
        ])->assertSessionHasErrors('payment_reference');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_admin_payment_review_records_reviewer_and_notifies_customer(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $proof = $this->fakePng('payment.png')->store('payment-proofs', 'local');
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-PAYMENT-1',
            'status' => 'pending',
            'payment_method' => 'gcash',
            'payment_status' => 'pending_verification',
            'payment_reference' => '9876543210',
            'payment_reference_normalized' => '9876543210',
            'payment_proof_path' => $proof,
            'total_amount' => 500,
        ]);

        $this->actingAs($admin)->put(route('admin.orders.status', $order), [
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_review_notes' => 'Reference and amount matched the receipt.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame($admin->id, $order->payment_verified_by);
        $this->assertNotNull($order->payment_verified_at);
        Notification::assertSentTo($customer, OrderPaymentReviewed::class);
    }

    public function test_password_reset_send_response_does_not_reveal_account_presence(): void
    {
        $user = User::factory()->create(['phone_number' => '+639171234567']);

        $known = $this->postJson(route('forgot.send-otp'), ['phone_number' => '0917 123 4567'])
            ->assertOk()
            ->json();
        $unknown = $this->postJson(route('forgot.send-otp'), ['phone_number' => '0999 000 0000'])
            ->assertOk()
            ->json();

        $this->assertSame($known, $unknown);
        $this->assertDatabaseHas('password_reset_otps', ['phone_number' => '+639171234567']);
        $this->assertSame(1, DB::table('password_reset_otps')->count());
        $this->assertNotNull($user);
    }

    public function test_password_reset_code_locks_after_five_wrong_attempts(): void
    {
        User::factory()->create(['phone_number' => '+639171234567']);
        $otpId = DB::table('password_reset_otps')->insertGetId([
            'phone_number' => '+639171234567',
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('forgot.verify-otp'), [
                'phone_number' => '09171234567',
                'otp' => '000000',
            ])->assertUnprocessable();
        }

        $record = DB::table('password_reset_otps')->find($otpId);
        $this->assertSame(5, (int) $record->attempts);
        $this->assertNotNull($record->locked_at);

        $this->postJson(route('forgot.verify-otp'), [
            'phone_number' => '09171234567',
            'otp' => '123456',
        ])->assertUnprocessable();
    }

    public function test_registration_normalizes_and_deduplicates_philippine_mobile_numbers(): void
    {
        $this->postJson(route('register.submit'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test',
            'phone_number' => '0917 123 4567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.test',
            'phone_number' => '+639171234567',
        ]);

        auth()->logout();
        $this->postJson(route('register.submit'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.test',
            'phone_number' => '+63 917 123 4567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('phone_number');
    }

    private function product(int $stock): Product
    {
        return Product::query()->create([
            'name' => 'Payment Test Plant',
            'price' => 100,
            'stock_qty' => $stock,
            'category' => 'Plants',
            'is_active' => true,
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
        );
    }
}
