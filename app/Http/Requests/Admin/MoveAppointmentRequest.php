<?php

namespace App\Http\Requests\Admin;

use App\Rules\DispatchSlot;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The staff form offers a date picker and a list of dispatch slots, because a
 * free-text datetime is how a crew ends up booked for 03:17. The action itself
 * takes one canonical `appointment_at`, so the two controls are joined here
 * rather than in the browser - the page then needs no JavaScript to submit.
 */
class MoveAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The route is behind the `staff` middleware.
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('move_date') && $this->filled('move_time')) {
            $this->merge([
                'appointment_at' => $this->string('move_date').' '.$this->string('move_time').':00',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'appointment_at' => ['required', 'date', 'after:now', new DispatchSlot],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_at.required' => 'Choose a date and a visit time.',
            'appointment_at.after' => 'A visit can only be moved to a time still ahead of us.',
        ];
    }
}
