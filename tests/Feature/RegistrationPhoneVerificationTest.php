<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistrationOtpService;
use App\Services\SmsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\Fakes\FakeSmsService;
use Tests\TestCase;

class RegistrationPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_customer_remains_logged_out_after_successful_phone_verification(): void
    {
        $sms = new FakeSmsService(true);
        $this->app->instance(SmsService::class, $sms);

        $this->postJson(route('register.submit'), $this->registrationData())
            ->assertCreated()
            ->assertJsonPath('verification_required', true);

        $user = User::query()->where('email', 'juan@example.test')->firstOrFail();
        preg_match('/\b(\d{6})\b/', $sms->messages[0]['message'], $matches);
        $sentCode = $matches[1] ?? null;
        $this->assertSame('+639171234567', $sms->messages[0]['to']);
        $this->assertGuest();
        $this->assertNull($user->phone_verified_at);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $sentCode);

        $otp = DB::table('registration_otps')->where('user_id', $user->id)->first();
        $this->assertNotNull($otp);
        $this->assertNotSame($sentCode, $otp->otp);
        $this->assertTrue(Hash::check((string) $sentCode, $otp->otp));

        $this->postJson(route('register.verify'), ['otp' => $sentCode])
            ->assertOk()
            ->assertJsonPath('message', 'Your mobile number has been verified. Please sign in to continue.')
            ->assertJsonPath('redirectUrl', route('login'))
            ->assertSessionHas('status', 'Your mobile number has been verified. Please sign in to continue.');

        $this->assertGuest();
        $this->assertFalse(session()->has('pending_registration_user_id'));
        $this->assertFalse(session()->has('pending_registration_phone'));
        $this->assertNotNull($user->refresh()->phone_verified_at);
        $this->assertNotNull(DB::table('registration_otps')->where('id', $otp->id)->value('used_at'));
    }

    public function test_issued_code_expires_ten_minutes_after_textbee_accepts_the_message(): void
    {
        $this->freezeTime();
        $sms = new class extends SmsService
        {
            public function send(string $to, string $message): bool
            {
                // Simulate provider latency so expiry must be based on the
                // moment TextBee accepts the message, not an earlier timestamp.
                Carbon::setTestNow(now()->addSeconds(2));

                return true;
            }
        };
        $this->app->instance(SmsService::class, $sms);
        $user = User::factory()->create([
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);

        $result = $this->app->make(RegistrationOtpService::class)->issue($user);
        $otp = DB::table('registration_otps')->where('user_id', $user->id)->first();

        $this->assertSame(RegistrationOtpService::ISSUED, $result);
        $this->assertIsObject($otp);
        $this->assertSame(
            600.0,
            Carbon::parse($otp->sent_at)
                ->diffInSeconds(Carbon::parse($otp->expires_at), false),
        );
    }

    public function test_unverified_customer_cannot_log_in_with_the_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@example.test',
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);

        $this->postJson(route('login.submit'), [
            'email' => 'pending@example.test',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);

        $this->assertGuest();
        $this->assertSame($user->id, session('pending_registration_user_id'));
        $this->assertSame('+639171234567', session('pending_registration_phone'));
    }

    public function test_resending_registration_otp_invalidates_the_previous_code(): void
    {
        $sms = new FakeSmsService(true, true, true);
        $this->app->instance(SmsService::class, $sms);

        $this->postJson(route('register.submit'), $this->registrationData())->assertCreated();
        $firstOtpId = DB::table('registration_otps')->value('id');

        $this->postJson(route('register.resend'))->assertOk();
        $this->postJson(route('register.resend'))->assertOk();
        $this->postJson(route('register.resend'))->assertTooManyRequests();

        $this->assertCount(3, $sms->messages);
        $this->assertSame(3, DB::table('registration_otps')->count());
        $this->assertNotNull(DB::table('registration_otps')->where('id', $firstOtpId)->value('used_at'));
    }

    public function test_registration_code_locks_after_five_wrong_attempts(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);
        $otpId = DB::table('registration_otps')->insertGetId([
            'user_id' => $user->id,
            'phone_number' => '+639171234567',
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession([
                'pending_registration_user_id' => $user->id,
                'pending_registration_phone' => '+639171234567',
            ])
                ->postJson(route('register.verify'), ['otp' => '000000'])
                ->assertUnprocessable();
        }

        $record = DB::table('registration_otps')->find($otpId);
        $this->assertIsObject($record);
        $this->assertSame(5, (int) $record->attempts);
        $this->assertNotNull($record->locked_at);

        $this->withSession([
            'pending_registration_user_id' => $user->id,
            'pending_registration_phone' => '+639171234567',
        ])
            ->postJson(route('register.verify'), ['otp' => '123456'])
            ->assertUnprocessable();
        $this->assertGuest();
        $this->assertNull($user->refresh()->phone_verified_at);
    }

    public function test_verification_page_has_native_otp_form_semantics(): void
    {
        $html = $this->withSession([
            'pending_registration_user_id' => 123,
            'pending_registration_phone' => '+639171234567',
        ])
            ->get(route('register.verification'))
            ->assertOk()
            ->getContent();

        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $form = $document->getElementById('registration-verification-form');
        $resendForm = $document->getElementById('registration-resend-form');
        $input = $document->getElementById('registration-otp-code');
        $this->assertNotNull($form);
        $this->assertNotNull($resendForm);
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame('post', strtolower($resendForm->getAttribute('method')));
        $this->assertSame(route('register.resend'), $resendForm->getAttribute('action'));
        $this->assertSame('otp', $input->getAttribute('name'));
        $this->assertSame('one-time-code', $input->getAttribute('autocomplete'));
        $this->assertTrue($input->hasAttribute('required'));
    }

    public function test_social_login_cannot_create_an_account_without_phone_verification(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'new-social@example.test',
            'email_verified' => true,
        ]));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->get(route('register'))
            ->assertSeeText('Register with your mobile number first, then you can use social sign-in.');

        $this->assertDatabaseMissing('users', ['email' => 'new-social@example.test']);
        $this->assertGuest();
    }

    public function test_existing_verified_customer_can_still_use_social_login(): void
    {
        $user = User::factory()->create([
            'email' => 'social@example.test',
            'phone_number' => '+639171234567',
            'phone_verified_at' => now(),
            'role' => 'user',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'social@example.test',
            'email_verified' => true,
        ]));

        $this->get(route('social.callback', 'google'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_unverified_customer_cannot_bypass_otp_with_social_login(): void
    {
        $user = User::factory()->create([
            'email' => 'pending-social@example.test',
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'pending-social@example.test',
            'email_verified' => true,
        ]));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('register.verification'));

        $this->assertGuest();
        $this->assertSame($user->id, session('pending_registration_user_id'));
    }

    public function test_expired_registration_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);
        DB::table('registration_otps')->insert([
            'user_id' => $user->id,
            'phone_number' => '+639171234567',
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subSecond(),
            'sent_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->withSession([
            'pending_registration_user_id' => $user->id,
            'pending_registration_phone' => '+639171234567',
        ])->postJson(route('register.verify'), ['otp' => '123456'])->assertUnprocessable();

        $this->assertGuest();
        $this->assertNull($user->refresh()->phone_verified_at);
    }

    public function test_failed_resend_keeps_the_last_delivered_code_valid(): void
    {
        $sms = new FakeSmsService(true, false);
        $this->app->instance(SmsService::class, $sms);

        $this->postJson(route('register.submit'), $this->registrationData())->assertCreated();
        preg_match('/\b(\d{6})\b/', $sms->messages[0]['message'], $matches);
        $firstCode = $matches[1] ?? null;
        $this->postJson(route('register.resend'))->assertStatus(503);
        $this->postJson(route('register.verify'), ['otp' => $firstCode])->assertOk();

        $this->assertGuest();
        $this->assertNotNull(
            User::query()->where('email', 'juan@example.test')->value('phone_verified_at'),
        );
    }

    public function test_failed_initial_sms_delivery_does_not_leave_an_unverified_account(): void
    {
        $sms = new FakeSmsService(false);
        $this->app->instance(SmsService::class, $sms);

        $this->postJson(route('register.submit'), $this->registrationData())
            ->assertStatus(503)
            ->assertJsonPath('message', 'We could not send your verification code. Please try again.');

        $this->assertDatabaseMissing('users', ['email' => 'juan@example.test']);
        $this->assertSame(0, DB::table('registration_otps')->count());
        $this->assertGuest();
    }

    public function test_initial_sms_exception_does_not_leave_an_unverified_account(): void
    {
        $sms = new FakeSmsService(new RuntimeException('provider unavailable'));
        $this->app->instance(SmsService::class, $sms);

        $this->postJson(route('register.submit'), $this->registrationData())->assertStatus(503);

        $this->assertDatabaseMissing('users', ['email' => 'juan@example.test']);
        $this->assertSame(0, DB::table('registration_otps')->count());
        $this->assertGuest();
    }

    public function test_an_abandoned_unverified_registration_can_be_reclaimed_after_one_hour(): void
    {
        $abandoned = User::factory()->create([
            'email' => 'abandoned@example.test',
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
            'created_at' => now()->subMinutes(61),
            'updated_at' => now()->subMinutes(61),
        ]);
        DB::table('registration_otps')->insert([
            'user_id' => $abandoned->id,
            'phone_number' => $abandoned->phone_number,
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app->instance(SmsService::class, new FakeSmsService(true));

        $this->postJson(route('register.submit'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.test',
            'phone_number' => '+639171234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
        ])->assertCreated();

        $replacement = User::query()->where('email', 'maria@example.test')->firstOrFail();
        $this->assertNotSame($abandoned->id, $replacement->id);
        $this->assertDatabaseMissing('users', ['id' => $abandoned->id]);
        $this->assertDatabaseMissing('registration_otps', ['user_id' => $abandoned->id]);
        $this->assertSame(1, User::query()->where('phone_number', '+639171234567')->count());
    }

    public function test_an_expired_pending_registration_cannot_verify_an_otherwise_valid_code(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
            'created_at' => now()->subMinutes(61),
            'updated_at' => now()->subMinutes(61),
        ]);
        DB::table('registration_otps')->insert([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number,
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession([
            'pending_registration_user_id' => $user->id,
            'pending_registration_phone' => $user->phone_number,
        ])->postJson(route('register.verify'), ['otp' => '123456'])->assertUnprocessable();

        $this->assertGuest();
        $this->assertNull($user->refresh()->phone_verified_at);
    }

    public function test_the_database_rejects_duplicate_phone_numbers_when_validation_is_bypassed(): void
    {
        User::factory()->create([
            'email' => 'first@example.test',
            'phone_number' => '+639171234567',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        User::query()->create([
            'name' => 'Second Customer',
            'email' => 'second@example.test',
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'password' => Hash::make('password'),
            'role' => 'user',
            'account_type' => 'Customer',
        ]);
    }

    public function test_the_database_rejects_equivalent_philippine_phone_numbers_when_model_validation_is_bypassed(): void
    {
        User::factory()->create([
            'email' => 'first@example.test',
            'phone_number' => '+639171234567',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        User::query()->create([
            'name' => 'Second Customer',
            'email' => 'second@example.test',
            'phone_number' => '0917 123 4567',
            'phone_verified_at' => null,
            'password' => Hash::make('password'),
            'role' => 'user',
            'account_type' => 'Customer',
        ]);
    }

    public function test_resend_delivery_limit_is_measured_from_the_time_textbee_accepted_each_message(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+639171234567',
            'phone_verified_at' => null,
            'role' => 'user',
        ]);
        foreach (range(1, 3) as $offset) {
            DB::table('registration_otps')->insert([
                'user_id' => $user->id,
                'phone_number' => $user->phone_number,
                'otp' => Hash::make((string) (100000 + $offset)),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'sent_at' => now()->subMinute(),
                'created_at' => now()->subMinutes(11),
                'updated_at' => now()->subMinute(),
            ]);
        }
        $sms = new FakeSmsService(true);
        $this->app->instance(SmsService::class, $sms);

        $result = $this->app->make(RegistrationOtpService::class)->issue($user);

        $this->assertSame(RegistrationOtpService::LIMITED, $result);
        $this->assertCount(0, $sms->messages);
    }

    public function test_migration_rejects_equivalent_legacy_phone_duplicates_before_mutating_the_schema_or_accounts(): void
    {
        $connectionName = 'otp_migration_preflight';
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $schema = $connection->getSchemaBuilder();
        $schema->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('password');
            $table->string('account_type')->nullable();
            $table->string('role')->default('user');
            $table->timestamps();
        });
        $connection->table('users')->insert([
            [
                'name' => 'First Legacy Customer',
                'email' => 'first-legacy@example.test',
                'phone_number' => '09171234567',
                'password' => 'not-used',
                'account_type' => 'Customer',
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Second Legacy Customer',
                'email' => 'second-legacy@example.test',
                'phone_number' => '+639171234567',
                'password' => 'not-used',
                'account_type' => 'Customer',
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::setDefaultConnection($connectionName);

        try {
            $migration = require base_path('database/migrations/2026_09_17_000001_add_phone_verification_to_users.php');
            $migration->up();
            $this->fail('Expected the migration to reject equivalent legacy phone numbers.');
        } catch (\LogicException) {
            $this->assertFalse($schema->hasTable('registration_otps'));
            $this->assertFalse($schema->hasColumn('users', 'phone_verified_at'));
            $this->assertSame('09171234567', $connection->table('users')->where('email', 'first-legacy@example.test')->value('phone_number'));
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge($connectionName);
        }
    }

    /** @return array<string, mixed> */
    private function registrationData(): array
    {
        return [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test',
            'phone_number' => '0917 123 4567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
        ];
    }
}
