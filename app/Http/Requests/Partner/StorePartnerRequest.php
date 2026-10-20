<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'max:255', Password::defaults()],
            'partner_level_id' => ['required', 'integer', 'exists:partner_levels,id'],
            'sponsor_id' => ['nullable', 'integer', 'exists:partners,id'],
            'discount_override_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
