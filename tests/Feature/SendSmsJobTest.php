<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use RuntimeException;
use Tests\Fakes\FakeSmsService;
use Tests\TestCase;

class SendSmsJobTest extends TestCase
{
    public function test_job_uses_the_priority_sms_queue_with_short_retries(): void
    {
        $job = new SendSmsJob('+639171234567', 'Ferosa: Test notification.');

        $this->assertSame('sms', $job->queue);
        $this->assertSame([5, 15], $job->backoff());
    }

    public function test_local_workers_poll_sms_before_normal_jobs(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $worker = file_get_contents(base_path('start-worker.bat'));

        foreach (['dev', 'dev:logs'] as $script) {
            $command = implode(' ', $composer['scripts'][$script]);

            $this->assertStringContainsString('queue:work --queue=sms,default --sleep=1 --tries=3', $command);
        }

        $this->assertIsString($worker);
        $this->assertStringContainsString('queue:work --queue=sms,default --sleep=1 --tries=3', $worker);
    }

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
