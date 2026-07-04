<?php

namespace App\Http\Requests\PartnerLevel;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission enforced by route middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'between:1,10', 'unique:partner_levels,level'],
            'discount_percent' => ['required', 'numeric', 'between:0,100'],
            'commission_percent' => ['nullable', 'numeric', 'between:0,100'],
            'sponsor_bonus_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
