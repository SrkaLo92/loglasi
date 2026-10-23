<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Broj telefona koji može da se svede na E.164 (srpski ili strani sa pozivnim brojem). */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Phone::isValid($value)) {
            $fail('Unesite ispravan broj telefona, npr. 065 123 4567.');
        }
    }
}
