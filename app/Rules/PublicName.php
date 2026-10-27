<?php

namespace App\Rules;

use App\Support\Accounts\PublicName as Name;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A public name without an e-mail address or a phone number (those go in their own fields). */
class PublicName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Name::hasContact($value)) {
            $fail('Ime ne sme da sadrži mejl adresu ni broj telefona. Njih upišite u posebna polja.');
        }
    }
}
