<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentBooked extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function build(): self
    {
        return $this->subject('Your Ferosa appointment is scheduled')
            ->view('mail.appointment-booked');
    }
}
