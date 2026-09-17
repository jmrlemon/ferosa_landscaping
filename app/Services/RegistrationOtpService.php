<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationOtpService
{
    public const ISSUED = 'issued';

    public const LIMITED = 'limited';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const PENDING_REGISTRATION_LIFETIME_MINUTES = 60;

    private const MAX_ATTEMPTS = 5;

    private const MAX_SENDS_PER_WINDOW = 3;

    private const EXPIRY_MINUTES = 10;

    public function __construct(private readonly SmsService $sms) {}

    public function issue(User $user): string
    {
        $phone = (string) $user->phone_number;
        if ($phone === '' || $user->phone_verified_at !== null) {
            return self::FAILED;
        }

        return DB::transaction(function () use ($user, $phone): string {
            // Serialize sends for one account through delivery. Otherwise two
            // resends can both pass the cap and invalidate each other while
            // their SMS messages arrive out of order.
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (! $lockedUser || $lockedUser->phone_verified_at !== null) {
                return self::FAILED;
            }

            if ($this->hasExpiredPendingRegistration($lockedUser)) {
                return self::EXPIRED;
            }

            $recentSends = DB::table('registration_otps')
                ->where('phone_number', $phone)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', now()->subMinutes(self::EXPIRY_MINUTES))
                ->count();

            if ($recentSends >= self::MAX_SENDS_PER_WINDOW) {
                return self::LIMITED;
            }

            $otp = (string) random_int(100000, 999999);
            $otpId = DB::table('registration_otps')->insertGetId([
                'user_id' => $user->id,
                'phone_number' => $phone,
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sent = $this->sms->send(
                $phone,
                "Your Ferosa Landscaping registration code is: {$otp}. Valid for 10 minutes."
            );

            if (! $sent) {
                DB::table('registration_otps')->where('id', $otpId)->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

                // A failed resend must not destroy the last delivered code.
                return self::FAILED;
            }

            DB::table('registration_otps')->where('id', $otpId)->update([
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('registration_otps')
                ->where('user_id', $user->id)
                ->where('id', '!=', $otpId)
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return self::ISSUED;
        });
    }

    public function verify(int $userId, string $phone, string $otp): ?User
    {
        return DB::transaction(function () use ($userId, $phone, $otp): ?User {
            $user = User::query()
                ->whereKey($userId)
                ->where('phone_number', $phone)
                ->whereNull('phone_verified_at')
                ->where('created_at', '>', now()->subMinutes(self::PENDING_REGISTRATION_LIFETIME_MINUTES))
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return null;
            }

            $record = DB::table('registration_otps')
                ->where('user_id', $user->id)
                ->where('phone_number', $phone)
                ->whereNotNull('sent_at')
                ->whereNull('used_at')
                ->whereNull('locked_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $record || (int) $record->attempts >= self::MAX_ATTEMPTS) {
                return null;
            }

            if (! Hash::check($otp, $record->otp)) {
                $attempts = (int) $record->attempts + 1;
                DB::table('registration_otps')->where('id', $record->id)->update([
                    'attempts' => $attempts,
                    'locked_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
                    'updated_at' => now(),
                ]);

                return null;
            }

            DB::table('registration_otps')->where('id', $record->id)->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

            $user->forceFill(['phone_verified_at' => now()])->save();

            return $user;
        });
    }

    private function hasExpiredPendingRegistration(User $user): bool
    {
        return $user->created_at === null
            || $user->created_at->lte(now()->subMinutes(self::PENDING_REGISTRATION_LIFETIME_MINUTES));
    }
}
