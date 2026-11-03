<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'buyer_name' => 'required|string|max:255',
            'buyer_rut' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', (string)$value));
                    if (strlen($clean) < 7 || strlen($clean) > 9) {
                        $fail('El RUT ingresado no tiene un largo válido (7 a 9 caracteres con DV).');
                        return;
                    }
                    $body = substr($clean, 0, -1);
                    $dv = substr($clean, -1);
                    if (!ctype_digit($body)) {
                        $fail('El cuerpo del RUT solo debe contener números.');
                        return;
                    }
                    $numBody = intval($body);
                    if ($numBody < 100000 || $numBody > 99999999) {
                        $fail('El número de RUT no está dentro de un rango válido chileno.');
                        return;
                    }
                    $sum = 0;
                    $mul = 2;
                    for ($i = strlen($body) - 1; $i >= 0; $i--) {
                        $sum += intval($body[$i]) * $mul;
                        $mul = ($mul === 7) ? 2 : $mul + 1;
                    }
                    $expected = 11 - ($sum % 11);
                    $dvExpected = match ($expected) {
                        11 => '0',
                        10 => 'K',
                        default => (string)$expected,
                    };
                    if ($dv !== $dvExpected) {
                        $fail('El RUT ingresado no es válido (dígito verificador incorrecto).');
                    }
                },
            ],
            'buyer_email' => 'required|email|max:255',
            'buyer_phone' => 'required|string|max:255',
            'company_unit' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable',
            'items.*.sku' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ];
    }
}

