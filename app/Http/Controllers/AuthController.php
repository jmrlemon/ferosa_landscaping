<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\RegistrationOtpService;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        // `?view=forgot` deep-links straight into the OTP reset panel, so signed-in
        // users sent here from the account page do not have to hunt for the link.
        return view('auth.auth', [
            'active' => $request->query('view') === 'forgot' ? 'forgot' : 'login',
        ]);
    }

    public function showRegister(): View
    {
        return view('auth.auth', ['active' => 'signup']);
    }

    public function login(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid email or password.'], 422);
            }

            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $user = Auth::user();
        if ($user?->isUser() && $user->phone_verified_at === null) {
            $phone = (string) $user->phone_number;
            Auth::logout();
            $request->session()->put([
                'pending_registration_user_id' => $user->id,
                'pending_registration_phone' => $phone,
            ]);

            $payload = [
                'message' => 'Verify your mobile number before signing in.',
                'verification_required' => true,
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 403);
            }

            return redirect()->route('register.verification')->withErrors(['email' => $payload['message']]);
        }

        $request->session()->regenerate();
        $redirectUrl = $user?->isStaffOrAdmin() ? route('admin.dashboard') : route('home');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'redirectUrl' => $redirectUrl]);
        }

        return redirect()->to($redirectUrl);
    }

    public function register(RegisterRequest $request, RegistrationOtpService $verification): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $nameParts = array_filter([$data['first_name'], $data['middle_name'] ?? null, $data['last_name']]);
        try {
            $user = DB::transaction(function () use ($data, $nameParts): User {
                User::query()
                    ->where('role', 'user')
                    ->whereNull('phone_verified_at')
                    ->whereNotNull('created_at')
                    ->where('created_at', '<=', now()->subMinutes(RegistrationOtpService::PENDING_REGISTRATION_LIFETIME_MINUTES))
                    ->where(function ($query) use ($data): void {
                        $query->where('email', $data['email'])
                            ->orWhereIn('phone_number', PhoneNumber::lookupCandidates($data['phone_number']));
                    })
                    ->delete();

                return User::query()->create([
                    'name' => implode(' ', $nameParts),
                    'email' => $data['email'],
                    'phone_number' => $data['phone_number'],
                    'phone_verified_at' => null,
                    'password' => Hash::make($data['password']),
                    'account_type' => 'Customer',
                    'role' => 'user',
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            $message = 'That email address or mobile number is already connected to an account.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['phone_number' => [$message]]], 422)
                : back()->withErrors(['phone_number' => $message])->withInput($request->except('password', 'password_confirmation'));
        }

        try {
            $result = $verification->issue($user);
        } catch (Throwable $exception) {
            report($exception);
            $result = RegistrationOtpService::FAILED;
        }

        if ($result !== RegistrationOtpService::ISSUED) {
            DB::transaction(function () use ($user): void {
                DB::table('registration_otps')->where('user_id', $user->id)->delete();
                $user->delete();
            });

            $message = 'We could not send your verification code. Please try again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : back()->withErrors(['phone_number' => $message])->withInput($request->except('password', 'password_confirmation'));
        }

        $request->session()->put([
            'pending_registration_user_id' => $user->id,
            'pending_registration_phone' => $user->phone_number,
        ]);
        $payload = [
            'ok' => true,
            'verification_required' => true,
            'message' => 'We sent a 6-digit verification code to your mobile number.',
            'redirectUrl' => route('register.verification'),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return redirect()->route('register.verification')->with('status', $payload['message']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Only this one intent is honoured; anything else falls back to plain login
        // so the field can never be used as an open redirect.
        if ($request->input('view') === 'forgot') {
            return redirect()->route('login', ['view' => 'forgot']);
        }

        return redirect()->route('login');
    }

    /**
     * Compatibility shim for old `GET /logout` links.
     *
     * A GET cannot carry a CSRF token, so on its own this route let any site
     * force a visitor to sign out with `<img src=".../logout">`. Fetch metadata
     * closes that: the browser sets these headers itself and script cannot
     * forge them, so we can tell a real navigation from an embedded request.
     * They are absent on older browsers, which we still honour rather than
     * stranding a signed-in user on a dead link.
     */
    public function logoutFallback(Request $request): RedirectResponse
    {
        $site = $request->header('Sec-Fetch-Site');
        $dest = $request->header('Sec-Fetch-Dest');

        $isTopLevelNavigation = $site === null
            || (in_array($site, ['none', 'same-origin', 'same-site'], true) && $dest !== 'image');

        if ($isTopLevelNavigation && Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'You have been signed out.');
        }

        if (Auth::check()) {
            // Cross-site attempt: leave the session intact and send them back.
            return redirect()->route('home');
        }

        return redirect()->route('login');
    }
}
