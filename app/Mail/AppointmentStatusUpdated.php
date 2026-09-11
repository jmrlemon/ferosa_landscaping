<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function build(): self
    {
        $label = ucfirst($this->appointment->status);

        return $this->subject("Your Ferosa appointment is now: {$label}")
            ->view('mail.appointment-status-updated');
    }
}
