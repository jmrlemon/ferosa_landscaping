<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_textbee_uses_the_current_account_level_send_contract(): void
    {
        config()->set('services.sms.driver', 'textbee');
        config()->set('services.textbee.api_key', 'test-api-key');
        config()->set('services.textbee.device_id', 'test-device-id');

        Http::fake([
            'https://api.textbee.dev/*' => Http::response(['data' => ['success' => true]], 200),
        ]);

        $sent = app(SmsService::class)->send('0917 123 4567', 'Your reset code is 123456.');

        $this->assertTrue($sent);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.textbee.dev/api/v1/gateway/send-sms'
                && $request->hasHeader('x-api-key', 'test-api-key')
                && $request['recipients'] === ['+639171234567']
                && $request['message'] === 'Your reset code is 123456.'
                && $request['deviceId'] === 'test-device-id';
        });
    }
}
