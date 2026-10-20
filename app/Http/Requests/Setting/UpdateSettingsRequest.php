<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'currency_symbol' => ['nullable', 'string', 'max:4'],
            'tax_percent' => ['nullable', 'numeric', 'between:0,100'],
            'default_partner_discount' => ['nullable', 'numeric', 'between:0,100'],
            'default_commission_percent' => ['nullable', 'numeric', 'between:0,100'],
            'default_sponsor_bonus_percent' => ['nullable', 'numeric', 'between:0,100'],
            'max_partner_depth' => ['nullable', 'integer', 'between:1,10'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:16'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
