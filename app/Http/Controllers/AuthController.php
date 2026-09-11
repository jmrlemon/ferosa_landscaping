<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

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

        $request->session()->regenerate();
        $redirectUrl = Auth::user()?->isStaffOrAdmin() ? route('admin.dashboard') : route('home');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'redirectUrl' => $redirectUrl]);
        }

        return redirect()->to($redirectUrl);
    }

    public function register(RegisterRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $nameParts = array_filter([$data['first_name'], $data['middle_name'] ?? null, $data['last_name']]);
        $user = User::create([
            'name' => implode(' ', $nameParts),
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
            'account_type' => 'Customer',
            'role' => 'user',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $redirectUrl = $user->isAdmin() ? route('admin.dashboard') : route('home');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'redirectUrl' => $redirectUrl]);
        }

        return redirect()->to($redirectUrl);
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
