<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthPageSecurityTest extends TestCase
{
    public function test_toast_messages_are_inserted_as_text_instead_of_html(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('messageText.textContent = String(message);', false)
            ->assertDontSee('toast.innerHTML = `${icons[type] || icons.info}<span>${message}</span>`;', false);
    }
}
