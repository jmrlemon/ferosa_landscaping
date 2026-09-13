<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_responses_include_baseline_security_headers(): void
    {
        $this->get('https://ferosa.test/')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'; upgrade-insecure-requests")
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_http_development_responses_do_not_claim_hsts(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader(
                'Content-Security-Policy',
                "base-uri 'self'; frame-ancestors 'self'; object-src 'none'"
            )
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
