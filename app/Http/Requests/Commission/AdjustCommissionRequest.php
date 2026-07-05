<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class AdjustCommissionRequest extends FormRequest
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
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'note' => ['required', 'string', 'max:255'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }
}
