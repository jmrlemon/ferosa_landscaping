<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Stringable;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_textbee_uses_the_current_account_level_send_contract(): void
    {
        $log = new class extends AbstractLogger
        {
            /** @var list<array{level: mixed, message: string|Stringable, context: array<string, mixed>}> */
            public array $records = [];

            /** @param array<string, mixed> $context */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };
        Log::swap($log);
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
        $this->assertCount(1, $log->records);
        $this->assertSame('info', $log->records[0]['level']);
        $this->assertSame('SMS delivery attempt completed.', $log->records[0]['message']);
        $this->assertSame('sms_delivery_attempt', $log->records[0]['context']['event']);
        $this->assertSame('textbee', $log->records[0]['context']['driver']);
        $this->assertTrue($log->records[0]['context']['accepted']);
        $this->assertIsInt($log->records[0]['context']['duration_ms']);
    }
}
