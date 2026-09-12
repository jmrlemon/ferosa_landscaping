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

    public function test_login_and_registration_are_real_forms_with_native_field_semantics(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $loginForm = $document->getElementById('login-form');
        $signupForm = $document->getElementById('signup-form');

        $this->assertNotNull($loginForm);
        $this->assertSame('post', strtolower($loginForm->getAttribute('method')));
        $this->assertSame('email', $document->getElementById('login-email')->getAttribute('name'));
        $this->assertSame('email', $document->getElementById('login-email')->getAttribute('type'));
        $this->assertTrue($document->getElementById('login-password')->hasAttribute('required'));

        $this->assertNotNull($signupForm);
        $this->assertSame('post', strtolower($signupForm->getAttribute('method')));
        $this->assertSame('first_name', $document->getElementById('signup-first-name')->getAttribute('name'));
        $this->assertSame('terms_accepted', $document->getElementById('signup-terms')->getAttribute('name'));
        $this->assertTrue($document->getElementById('signup-terms')->hasAttribute('required'));

        $this->assertStringContainsString("document.getElementById('login-form').addEventListener('submit'", $html);
        $this->assertStringContainsString("document.getElementById('signup-form').addEventListener('submit'", $html);
    }
}
