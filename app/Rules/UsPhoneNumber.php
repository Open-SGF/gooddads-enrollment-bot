<?php

declare(strict_types=1);

namespace App\Rules;

use Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

#[Attribute]
final class UsPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Regular expression for US phone number format (XXX) XXX-XXXX or XXX-XXX-XXXX or just XXXXXXXXXX
        $regex = '/^(?:\(\d{3}\)|\d{3})[-.\s]?\d{3}[-.\s]?\d{4}$/';
        $stringValue = is_scalar($value) ? (string) $value : '';

        if (! preg_match($regex, $stringValue)) {
            $fail('The :attribute must be a valid US phone number.');
        }
    }
}
