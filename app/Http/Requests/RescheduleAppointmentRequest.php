<?php

namespace App\Http\Requests;

use App\Rules\DispatchSlot;
use Illuminate\Support\Carbon;

/**
 * Moving an existing visit accepts one field: the new time. The service, the
 * fee and the owner all stay whatever the appointment already recorded, so
 * none of them are read from the form.
 *
 * A moved visit has to land on a published dispatch slot for exactly the
 * reason a new booking does - the crew is dispatched to those times and no
 * others.
 */
class RescheduleAppointmentRequest extends StoreScheduleRequest
{
    public function rules(): array
    {
        $minimumAppointmentAt = Carbon::now()->addHours(24)->format('Y-m-d H:i:s');

        return [
            'appointment_at' => [
                'required',
                'date',
                'after_or_equal:'.$minimumAppointmentAt,
                new DispatchSlot,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_at.after_or_equal' => 'A new visit time must also be at least 24 hours from now.',
        ];
    }
}
