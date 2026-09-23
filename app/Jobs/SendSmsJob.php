<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $to,
        private string $message,
    ) {
        $this->onQueue('sms');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(SmsService $sms): void
    {
        if (! $sms->send($this->to, $this->message)) {
            throw new \RuntimeException('SMS provider did not accept the message.');
        }
    }

    public function recipient(): string
    {
        return $this->to;
    }

    public function message(): string
    {
        return $this->message;
    }
}
