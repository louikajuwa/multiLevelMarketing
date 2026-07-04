<?php

namespace App\Http\Requests\PartnerLevel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerLevelRequest extends FormRequest
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
        $id = $this->route('partner_level')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'between:1,10', Rule::unique('partner_levels', 'level')->ignore($id)],
            'discount_percent' => ['required', 'numeric', 'between:0,100'],
            'commission_percent' => ['nullable', 'numeric', 'between:0,100'],
            'sponsor_bonus_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
