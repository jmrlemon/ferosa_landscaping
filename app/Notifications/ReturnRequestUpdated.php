<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReturnRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ReturnRequest $claim, private string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'return_request',
            'return_request_id' => $this->claim->id,
            'claim_number' => $this->claim->claim_number,
            'status' => $this->claim->status,
            'message' => $this->message(),
            'url' => route('returns.show', $this->claim, absolute: false),
        ];
    }

    public function toSmsMessage(): string
    {
        return 'Ferosa: '.$this->message().' View your account for details.';
    }

    private function message(): string
    {
        return match ($this->event) {
            'needs_information' => "We need more information for claim {$this->claim->claim_number}.",
            'approved' => "Claim {$this->claim->claim_number} was approved.",
            'rejected' => "Claim {$this->claim->claim_number} was not approved.",
            'replacement_dispatched' => "The replacement for claim {$this->claim->claim_number} is out for delivery.",
            'refund' => "A refund was recorded for claim {$this->claim->claim_number}.",
            'resolved' => "Claim {$this->claim->claim_number} is resolved.",
            default => "Claim {$this->claim->claim_number} was updated.",
        };
    }
}
