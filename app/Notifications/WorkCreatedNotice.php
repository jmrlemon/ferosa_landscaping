<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkCreatedNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $type,
        private string $message,
        private string $url,
        private ?int $orderId = null,
        private ?int $appointmentId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'order_id' => $this->orderId,
            'appointment_id' => $this->appointmentId,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
