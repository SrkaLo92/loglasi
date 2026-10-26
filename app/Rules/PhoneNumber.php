<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A phone number that can be brought to E.164 (Serbian, or foreign with a country code). */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Phone::isValid($value)) {
            $fail('Unesite ispravan broj telefona, npr. 065 123 4567.');
        }
    }
}
