<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketplaceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('marketplace');
        
        $multiEmailValidator = function ($attribute, $value, $fail) {
            if (empty($value)) return;
            $parts = preg_split('/[\s,;]+/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fail("El correo '{$email}' no tiene un formato de correo electrónico válido.");
                }
            }
        };

        return [
            'name' => 'required|string|max:255',
            'slug' => $id ? "required|string|max:255|unique:marketplaces,slug,{$id}" : 'required|string|max:255|unique:marketplaces,slug',
            'dealer_email' => ['nullable', 'string', 'max:2000', $multiEmailValidator],
            'quote_email' => ['nullable', 'string', 'max:2000', $multiEmailValidator],
            'internal_email' => ['nullable', 'string', 'max:2000', $multiEmailValidator],
            'password' => 'nullable|string|min:4',
            'logo_url' => 'nullable|string',
            'hero_image_url' => 'nullable|string',
            'primary_color' => 'nullable|string|max:50',
            'active' => 'boolean',
        ];
    }
}
