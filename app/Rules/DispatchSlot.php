<?php

namespace App\Rules;

use App\Models\Appointment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * A visit time has to be one of the times a crew is actually dispatched to.
 *
 * The booking form only offers Appointment::SLOT_TIMES, but that was once a
 * browser-side constraint alone: a posted 03:17 appointment was accepted and
 * scheduled a crew for the middle of the night. The times are checked server
 * side for the same reason prices and quantities are recalculated there.
 *
 * This lives in a rule rather than in one form request because customers book,
 * customers move their own visit, and staff move it for them - three entry
 * points that must agree on what a bookable time is.
 */
class DispatchSlot implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $time = Carbon::parse((string) $value)->format('H:i');
        } catch (\Throwable) {
            return; // The `date` rule already reports an unparseable value.
        }

        if (! in_array($time, Appointment::SLOT_TIMES, true)) {
            $fail('Please choose one of the available visit times: '.implode(', ', Appointment::SLOT_TIMES).'.');

            return;
        }

        // A closed day is not a dispatch slot either. Checked here rather than
        // in one form request so booking, moving and staff moving all agree.
        if (Appointment::isClosedOn(Carbon::parse((string) $value))) {
            $fail('We do not run visits on '.implode(' or ', Appointment::closedDayNames()).'. Please choose another day.');
        }
    }
}
