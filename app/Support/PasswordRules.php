<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /** @return array<int, string|Password> */
    public static function required(): array
    {
        return ['required', 'string', 'max:255', 'confirmed', self::strength()];
    }

    /** @return array<int, string|Password> */
    public static function optional(): array
    {
        return ['nullable', 'string', 'max:255', 'confirmed', self::strength()];
    }

    private static function strength(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}
