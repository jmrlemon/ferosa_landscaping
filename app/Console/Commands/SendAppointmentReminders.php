<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsJob;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send SMS reminders to customers with appointments tomorrow.';

    public function handle(): void
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        $appointments = Appointment::query()
            ->with(['user', 'serviceType'])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('appointment_at', [$tomorrowStart, $tomorrowEnd])
            ->whereNull('archived_at')
            ->get();

        foreach ($appointments as $appt) {
            if (! $appt->user->phone_number) {
                continue;
            }

            $service = $appt->serviceType->name ?? 'Service';
            $time = $appt->appointment_at->format('g:i A');

            SendSmsJob::dispatch(
                $appt->user->phone_number,
                "Ferosa Reminder: Your {$service} appointment is tomorrow at {$time}. See you then!"
            );
        }

        $this->info("Reminders queued for {$appointments->count()} appointment(s).");
    }
}
