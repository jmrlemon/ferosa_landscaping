<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * The team moved a visit the customer did not ask to move, so the new time has
 * to reach them rather than sit in the workspace. The old time is carried
 * along: "your visit is now on Friday" is only useful next to the day it was.
 */
class AppointmentMovedByTeam extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Appointment $appointment, private Carbon $previousAt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $service = $this->appointment->serviceType->name ?? 'Service';

        return [
            'type' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'service' => $service,
            'status' => $this->appointment->status,
            'message' => "Your {$service} visit has been moved from "
                .$this->previousAt->format('M d, Y g:i A').' to '
                .$this->appointment->appointment_at->format('M d, Y g:i A').'.',
            'url' => route('appointments', absolute: false),
        ];
    }
}
