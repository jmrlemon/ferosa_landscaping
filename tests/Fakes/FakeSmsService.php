<?php

namespace Tests\Fakes;

use App\Services\SmsService;
use Throwable;

final class FakeSmsService extends SmsService
{
    /** @var list<array{to: string, message: string}> */
    public array $messages = [];

    /** @var list<bool|Throwable> */
    private array $outcomes;

    public function __construct(bool|Throwable ...$outcomes)
    {
        $this->outcomes = $outcomes;
    }

    public function send(string $to, string $message): bool
    {
        $this->messages[] = ['to' => $to, 'message' => $message];
        $outcome = array_shift($this->outcomes) ?? true;

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
