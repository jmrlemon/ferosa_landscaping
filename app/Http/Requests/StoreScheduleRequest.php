<?php

namespace App\Http\Requests;

use App\Rules\DispatchSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minimumAppointmentAt = Carbon::now()->addHours(24)->format('Y-m-d H:i:s');

        return [
            'service_type_id' => ['required', 'exists:service_types,id'],
            'service_name' => ['nullable', 'string', 'max:255'], // legacy/compat
            'appointment_at' => [
                'required',
                'date',
                'after_or_equal:'.$minimumAppointmentAt,
                new DispatchSlot,
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_at.after_or_equal' => 'Appointments must be scheduled at least 24 hours in advance.',
        ];
    }
}
