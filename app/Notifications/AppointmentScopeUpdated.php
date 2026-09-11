<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentScopeUpdated extends Notification implements ShouldQueue
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
        $total = number_format((float) ($this->appointment->appointment_amount ?? 0), 2);

        return [
            'type' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'service' => $service,
            'status' => $this->appointment->status,
            'message' => "Your {$service} visit has been updated. The confirmed total is now PHP {$total}.",
            'url' => route('appointments', absolute: false),
        ];
    }
}
