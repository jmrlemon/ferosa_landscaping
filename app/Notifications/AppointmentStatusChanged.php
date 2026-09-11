<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $service = $this->appointment->serviceType->name ?? 'Service';

        $label = match ($this->appointment->status) {
            'confirmed' => 'confirmed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => $this->appointment->status,
        };

        return [
            'type' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'service' => $service,
            'status' => $this->appointment->status,
            'message' => "Your {$service} appointment has been {$label}.",
            'url' => route('appointments', absolute: false),
        ];
    }
}
