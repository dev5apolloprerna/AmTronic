<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * HSN codes are 4, 6 or 8 digits (which one depends on turnover / imports and
 * exports). 5- and 7-digit codes do not exist, so they are rejected.
 *
 * Used by both the Product Master and the Material Master so they cannot drift.
 */
class HsnCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // A JSON client (the Android app) may send 8536 as a number rather than a string.
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || ! preg_match('/^(?:\d{4}|\d{6}|\d{8})\z/', $value)) {
            $fail('The :attribute must be 4, 6 or 8 digits.');
        }
    }
}
