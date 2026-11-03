<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value));

        if (strlen($clean) < 2) {
            $fail('El RUT no es válido.');
            return;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);

        if (!ctype_digit($body)) {
            $fail('El RUT no es válido.');
            return;
        }

        $sum = 0;
        $multiplier = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);
        $computedDv = $remainder === 11 ? '0' : ($remainder === 10 ? 'K' : (string) $remainder);

        if ($computedDv !== $dv) {
            $fail('El RUT no es válido.');
        }
    }
}
