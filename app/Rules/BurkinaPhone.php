<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BurkinaPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Phone::isValid((string) $value)) {
            $fail('Le numéro doit comporter 8 chiffres (ex. 70 12 34 56).');
        }
    }
}
