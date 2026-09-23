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
            // The prepared estimator session is the authority for the service.
            // These optional legacy fields are accepted but never trusted.
            'service_type_id' => ['sometimes', 'integer', 'exists:service_types,id'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'appointment_at' => [
                'required',
                'date',
                'after_or_equal:'.$minimumAppointmentAt,
                new DispatchSlot,
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'site_province_code' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'site_city_code' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'site_barangay_code' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'site_street' => ['nullable', 'string', 'max:450'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_at.after_or_equal' => 'Appointments must be scheduled at least 24 hours in advance.',
        ];
    }
}
