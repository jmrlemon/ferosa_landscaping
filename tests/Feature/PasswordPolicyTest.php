<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_a_weak_password(): void
    {
        $this->postJson(route('register.submit'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test',
            'phone_number' => '09171234567',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
            'terms_accepted' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_customer_account_rejects_a_weak_new_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CurrentPassword1!')]);

        $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'CurrentPassword1!',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('CurrentPassword1!', $user->refresh()->password));
    }

    public function test_password_reset_rejects_a_weak_new_password(): void
    {
        User::factory()->create(['phone_number' => '+639171234567']);
        DB::table('password_reset_otps')->insert([
            'phone_number' => '+639171234567',
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('forgot.reset'), [
            'phone_number' => '09171234567',
            'otp' => '123456',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
