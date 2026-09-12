<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/client-ip', fn (Request $request) => $request->ip());
    }

    public function test_the_local_hosting_proxy_can_forward_the_real_client_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.42')
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSeeText('203.0.113.42');
    }

    public function test_an_external_client_cannot_spoof_its_ip_with_a_forwarded_header(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeader('X-Forwarded-For', '203.0.113.42')
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSeeText('198.51.100.20');
    }
}
