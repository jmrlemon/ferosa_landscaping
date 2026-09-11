<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetOtpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function sendOtp(Request $request, SmsService $sms): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $phone = PhoneNumber::normalize($data['phone_number']);
        $candidates = PhoneNumber::lookupCandidates($data['phone_number']);
        $genericResponse = [
            'ok' => true,
            'message' => 'If that mobile number is connected to an account, a reset code will be sent shortly.',
        ];

        $user = User::query()->whereIn('phone_number', $candidates)->first();
        if (! $user) {
            Hash::make((string) random_int(100000, 999999));

            return response()->json($genericResponse);
        }

        $recent = DB::table('password_reset_otps')
            ->whereIn('phone_number', $candidates)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->count();

        if ($recent >= 3) {
            return response()->json($genericResponse);
        }

        $otp = (string) random_int(100000, 999999);
        $otpId = DB::transaction(function () use ($candidates, $phone, $otp): int {
            DB::table('password_reset_otps')
                ->whereIn('phone_number', $candidates)
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return DB::table('password_reset_otps')->insertGetId([
                'phone_number' => $phone,
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes(10),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });

        if (! $sms->send($phone, "Your Ferosa Landscaping password reset code is: {$otp}. Valid for 10 minutes.")) {
            DB::table('password_reset_otps')->where('id', $otpId)->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
            Log::warning('Password reset OTP could not be delivered.', ['user_id' => $user->id]);
        }

        return response()->json($genericResponse);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $valid = DB::transaction(fn (): bool => $this->checkOtp(
            PhoneNumber::lookupCandidates($data['phone_number']),
            $data['otp'],
            consume: false,
        ));

        if (! $valid) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $candidates = PhoneNumber::lookupCandidates($data['phone_number']);
        $reset = DB::transaction(function () use ($candidates, $data): bool {
            $user = User::query()->whereIn('phone_number', $candidates)->lockForUpdate()->first();
            if (! $user || ! $this->checkOtp($candidates, $data['otp'], consume: true)) {
                return false;
            }

            $user->update(['password' => Hash::make($data['password'])]);

            // A reset is the one action a compromised user has left, so it has
            // to actually evict the attacker. Rotating the remember token kills
            // any long-lived cookie, and because sessions live in the database
            // we can drop the server-side records too - otherwise the old
            // session cookie keeps working with the new password in place.
            $user->setRememberToken(Str::random(60));
            $user->save();

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();

            return true;
        });

        if (! $reset) {
            return response()->json(['message' => 'Invalid or expired OTP. Please start over.'], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Password reset successfully.']);
    }

    /** @param list<string> $phoneCandidates */
    private function checkOtp(array $phoneCandidates, string $otp, bool $consume): bool
    {
        $record = DB::table('password_reset_otps')
            ->whereIn('phone_number', $phoneCandidates)
            ->whereNull('used_at')
            ->whereNull('locked_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $record || (int) $record->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($otp, $record->otp)) {
            $attempts = (int) $record->attempts + 1;
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'attempts' => $attempts,
                'locked_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
                'updated_at' => now(),
            ]);

            return false;
        }

        if ($consume) {
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }
}
