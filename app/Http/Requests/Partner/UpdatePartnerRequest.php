<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
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
            'partner_level_id' => ['required', 'integer', 'exists:partner_levels,id'],
            'discount_override_percent' => ['nullable', 'numeric', 'between:0,100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }
}
