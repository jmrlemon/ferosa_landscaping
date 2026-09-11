<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotFromAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_shows_current_password_and_forgot_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account')
            ->assertOk()
            ->assertSee('Current password')
            ->assertSee('Forgot password?')
            ->assertSee('forgot-password-form');
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put('/account', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(\Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put('/account', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'old-password-123',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Hash::check('brand-new-pass-1', $user->fresh()->password));
    }

    public function test_contact_only_save_still_works_without_password_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account', [
            'name' => 'Renamed Person',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed Person', $user->fresh()->name);
    }

    public function test_logout_with_forgot_intent_deep_links_into_reset_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout', ['view' => 'forgot'])
            ->assertRedirect(route('login', ['view' => 'forgot']));

        $this->assertGuest();
    }

    public function test_logout_ignores_unknown_view_values(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout', ['view' => 'https://evil.test'])
            ->assertRedirect(route('login'));
    }

    public function test_forgot_view_renders_reset_panel_as_active(): void
    {
        $this->get('/login?view=forgot')
            ->assertOk()
            ->assertSee('<div class="page active" id="page-forgot">', false);
    }

    public function test_plain_login_still_defaults_to_login_panel(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<div class="page active" id="page-login">', false);
    }

    /**
     * The root URL used to render the login pane itself. It now renders the
     * public landing page instead, so what still matters is that a guest hits
     * a working page rather than an error, and can reach sign-in from there.
     * `/login` continuing to render the auth panes is covered above.
     */
    public function test_root_route_sends_guests_somewhere_usable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'), false);
    }
}
