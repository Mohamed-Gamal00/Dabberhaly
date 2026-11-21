<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's Egyptian mobile number.
 *
 * Valid forms (spaces and dashes are ignored before matching):
 *   - local:        010 / 011 / 012 / 015 + 8 digits  (11 digits)
 *   - international: +20 / 20 + 1[0125] + 8 digits
 *     e.g. +201012345678, 201012345678
 *
 * Rejects everything else: wrong prefixes (013, 014, 016, ...),
 * wrong lengths, letters, and landlines (02...).
 *
 * The value is validated as given — it is NOT rewritten or
 * normalised. Storage keeps exactly what the user typed.
 */
class EgyptianMobileNumber implements ValidationRule
{
    /**
     * Applied after spaces and dashes have been stripped.
     */
    public const PATTERN = '/^(?:\+20|20|0)1[0125][0-9]{8}$/';

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $cleaned = preg_replace('/[\s\-]/', '', (string) $value);

        return (bool) preg_match(self::PATTERN, $cleaned);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail(__('validation.egyptian_mobile'));
        }
    }
}
