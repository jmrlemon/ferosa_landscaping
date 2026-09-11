<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // The root URL now lands on the catalogue, which queries products. The old
    // login-page response needed no tables at all, hence this was unused.
    use RefreshDatabase;

    /**
     * Smoke test: the front door serves a working page. The root URL redirects
     * guests to the public catalogue, so this follows the redirect rather than
     * expecting 200 from `/` directly.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->followingRedirects()
            ->get('/')
            ->assertStatus(200);
    }
}
