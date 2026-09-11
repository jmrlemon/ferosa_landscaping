<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const ALLOWED = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED), 404);

        $clientId = config("services.{$provider}.client_id");
        if (empty($clientId)) {
            return redirect()->route('login')
                ->withErrors(['email' => ucfirst($provider).' login is not configured yet. Please use email instead.']);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Social login failed. Please try again.']);
        }

        // The google_id/facebook_id columns were dropped in migration
        // 2026_04_13_000001, so email is the only join key that still exists.
        // That makes the provider's email the whole basis of identity here -
        // it has to be present and provider-verified before it is trusted.
        $email = $social->getEmail();

        if (! is_string($email) || $email === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Your '.ucfirst($provider).' account did not share an email address. Please sign in with your email instead.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $this->emailIsVerifiedByProvider($social)) {
            // Matching on an unverified address would let anyone who can set
            // that address on a provider account claim the local account -
            // including a staff or admin one.
            return redirect()->route('login')->withErrors([
                'email' => 'An account already uses this email. Please sign in with your password to continue.',
            ]);
        }

        if (! $user) {
            $user = User::create([
                'name' => $social->getName() ?? $social->getNickname() ?? 'User',
                'email' => $email,
                // The same migration restored password to NOT NULL. Social
                // accounts never use this value; it exists so the row is valid
                // and so the hash can never be guessed.
                'password' => Str::random(64),
                'account_type' => 'Customer',
                'role' => 'user',
            ]);
        }

        if ($this->emailIsVerifiedByProvider($social) && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Deliberately not forcing "remember me": a social sign-in is not an
        // opt-in to a long-lived cookie, and password resets cannot see it.
        Auth::login($user);
        request()->session()->regenerate();

        $redirectUrl = $user->isStaffOrAdmin()
            ? route('admin.dashboard')
            : route('home');

        return redirect()->to($redirectUrl);
    }

    /**
     * Whether the provider states it has verified the address it just handed us.
     *
     * Google (OIDC) returns `email_verified`; older payloads used
     * `verified_email`. Facebook returns neither, so a Facebook sign-in can
     * create a brand new account but can never take over an existing one.
     */
    private function emailIsVerifiedByProvider(SocialiteUser $social): bool
    {
        $raw = $social->getRaw();

        foreach (['email_verified', 'verified_email'] as $key) {
            if (array_key_exists($key, $raw)) {
                return filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }
}
