<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_over_ajax_returns_the_stored_message_instead_of_a_redirect(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->postJson('/messages', ['body' => 'Do you deliver to Samal?'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Do you deliver to Samal?')
            ->assertJsonPath('message.is_mine', true)
            ->assertJsonStructure(['message' => ['id', 'body', 'created_at', 'is_mine', 'sender']]);
    }

    public function test_plain_form_post_still_redirects_for_the_no_javascript_path(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->post('/messages', ['body' => 'Sent without javascript'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', ['body' => 'Sent without javascript']);
    }

    public function test_message_bodies_are_returned_raw_so_the_client_must_escape_them(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $payload = '<img src=x onerror=alert(1)> a < b';

        $this->actingAs($customer)->postJson('/messages', ['body' => $payload]);

        // The API returns the body verbatim; the chat renders it with
        // textContent, so the markup must survive the round trip unchanged
        // rather than being pre-escaped or stripped.
        $this->actingAs($customer)
            ->getJson('/messages/poll')
            ->assertOk()
            ->assertJsonPath('messages.0.body', $payload);
    }

    public function test_the_rendered_chat_page_escapes_existing_message_bodies(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'sender_id' => $customer->id,
            'body' => '<script>alert(1)</script>',
        ]);

        $html = $this->actingAs($customer)->get('/messages')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_staff_are_pushed_to_the_admin_inbox_rather_than_the_customer_chat(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/messages')->assertRedirect();
        $this->actingAs($staff)->postJson('/messages', ['body' => 'nope'])->assertForbidden();
    }
}
