<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '+63'.substr($digits, 1);
        }

        return str_starts_with(trim($value), '+') ? '+'.$digits : $digits;
    }

    /** @return list<string> */
    public static function lookupCandidates(string $value): array
    {
        $normalized = self::normalize($value);
        $digits = ltrim($normalized, '+');
        $candidates = [trim($value), $normalized, $digits];

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $candidates[] = '0'.substr($digits, 2);
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
