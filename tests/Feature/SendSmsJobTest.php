<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use RuntimeException;
use Tests\Fakes\FakeSmsService;
use Tests\TestCase;

class SendSmsJobTest extends TestCase
{
    public function test_job_sends_the_captured_recipient_and_message_through_the_sms_service(): void
    {
        $sms = new FakeSmsService(true);
        $job = new SendSmsJob(
            '+639171234567',
            'Ferosa: Your Lawn Care appointment was rescheduled.'
        );

        $job->handle($sms);

        $this->assertSame([
            [
                'to' => '+639171234567',
                'message' => 'Ferosa: Your Lawn Care appointment was rescheduled.',
            ],
        ], $sms->messages);
    }

    public function test_job_throws_when_the_sms_provider_rejects_the_message_so_the_queue_can_retry(): void
    {
        $sms = new FakeSmsService(false);
        $job = new SendSmsJob(
            '+639171234567',
            'Ferosa: Your appointment was rescheduled.'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMS provider did not accept the message.');

        $job->handle($sms);
    }
}
