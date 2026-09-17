<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\RegistrationOtpService;
use App\Support\PasswordRules;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^\+639\d{9}$/'],
            'password' => PasswordRules::required(),
            'terms_accepted' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_number')) {
            $this->merge(['phone_number' => PhoneNumber::normalize((string) $this->input('phone_number'))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = (string) $this->input('email');
            $phone = (string) $this->input('phone_number');
            if ($email !== '' && $this->blockingAccountQuery()->where('email', $email)->exists()) {
                $validator->errors()->add('email', 'That email address is already connected to an account.');
            }

            if ($phone !== '' && $this->blockingAccountQuery()->whereIn('phone_number', PhoneNumber::lookupCandidates($phone))->exists()) {
                $validator->errors()->add('phone_number', 'That mobile number is already connected to an account.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'Please read and accept the Terms and Conditions before creating an account.',
            'phone_number.regex' => 'Enter a valid Philippine mobile number, such as 0917 123 4567.',
            'phone_number.unique' => 'That mobile number is already connected to an account.',
        ];
    }

    /** @return Builder<User> */
    private function blockingAccountQuery(): Builder
    {
        return User::query()->where(function (Builder $query): void {
            $query->whereNotNull('phone_verified_at')
                ->orWhere('role', '!=', 'user')
                ->orWhereNull('created_at')
                ->orWhere('created_at', '>', now()->subMinutes(RegistrationOtpService::PENDING_REGISTRATION_LIFETIME_MINUTES));
        });
    }
}
