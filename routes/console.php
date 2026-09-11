<?php

use App\Console\Commands\SendAppointmentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send SMS reminders every day at 8 AM for appointments scheduled tomorrow
Schedule::command(SendAppointmentReminders::class)->dailyAt('08:00');

// Nightly database dump, keeping the last two weeks. MySQL here runs without
// binary logging, so these dumps are the only route back from data loss.
// NOTE: this only fires while `php artisan schedule:work` (or a cron/Task
// Scheduler entry calling `schedule:run`) is actually running.
Schedule::command('db:backup', ['--keep=14'])->dailyAt('02:00');
