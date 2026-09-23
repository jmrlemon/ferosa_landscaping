<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sidebar_logout_requires_confirmation(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-confirm-title="Log out?"', false)
            ->assertSee('data-confirm="Are you sure you want to log out of your Ferosa account?"', false)
            ->assertSee('data-confirm-action="Log out"', false)
            ->assertSee('id="ferosa-confirm"', false)
            ->assertSee('role="alertdialog"', false)
            ->assertSee('if (e.defaultPrevented) return;', false);
    }

    public function test_account_page_logout_requires_confirmation(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), 'data-confirm-title="Log out?"'));
    }

    public function test_admin_workspace_logout_requires_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.account.edit'))
            ->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'data-confirm-title="Log out?"'));
        $response->assertSee('id="ferosa-confirm"', false);
    }
}
