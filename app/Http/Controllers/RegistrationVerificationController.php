<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegistrationOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RegistrationVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(['pending_registration_user_id', 'pending_registration_phone'])) {
            return redirect()->route('register');
        }

        return view('auth.auth', ['active' => 'registration-otp']);
    }

    public function verify(Request $request, RegistrationOtpService $verification): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $userId = (int) $request->session()->get('pending_registration_user_id', 0);
        $phone = (string) $request->session()->get('pending_registration_phone', '');
        $user = $userId === 0 || $phone === '' ? null : $verification->verify($userId, $phone, $data['otp']);

        if (! $user) {
            $message = 'Invalid or expired verification code.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['otp' => $message]);
        }

        $request->session()->forget(['pending_registration_user_id', 'pending_registration_phone']);
        $request->session()->regenerate();
        $message = 'Your mobile number has been verified. Please sign in to continue.';

        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirectUrl' => route('login'),
            ]);
        }

        return redirect()->route('login')->with('status', $message);
    }

    public function resend(Request $request, RegistrationOtpService $verification): JsonResponse|RedirectResponse
    {
        $userId = (int) $request->session()->get('pending_registration_user_id', 0);
        $phone = (string) $request->session()->get('pending_registration_phone', '');
        $user = User::query()
            ->whereKey($userId)
            ->where('phone_number', $phone)
            ->whereNull('phone_verified_at')
            ->first();

        if (! $user) {
            return $this->resendError($request, 'Start registration again before requesting a code.', 422);
        }

        try {
            $result = $verification->issue($user);
        } catch (Throwable $exception) {
            report($exception);

            return $this->resendError($request, 'We could not send a new code. Please try again.', 503);
        }
        if ($result === RegistrationOtpService::LIMITED) {
            return $this->resendError($request, 'Too many codes were requested. Please wait 10 minutes.', 429);
        }

        if ($result === RegistrationOtpService::EXPIRED) {
            $request->session()->forget(['pending_registration_user_id', 'pending_registration_phone']);

            return $this->resendError($request, 'This registration has expired. Please register again.', 422, true);
        }

        if ($result !== RegistrationOtpService::ISSUED) {
            return $this->resendError($request, 'We could not send a new code. Please try again.', 503);
        }

        $message = 'A new verification code was sent to your mobile number.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : back()->with('status', $message);
    }

    private function resendError(Request $request, string $message, int $status, bool $registrationExpired = false): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $payload = ['message' => $message];
            if ($registrationExpired) {
                $payload['registration_expired'] = true;
            }

            return response()->json($payload, $status);
        }

        return back()->withErrors(['otp' => $message]);
    }
}
