<?php

namespace App\Http\Requests\Customer;

use App\Models\Partner;
use App\Services\PartnerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
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
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $customer = $this->route('customer');
            $partner = Partner::with('level')->find($customer->partner_id);
            if (! $partner) {
                return;
            }
            $cap = app(PartnerService::class)->effectiveDiscountPercent($partner);
            if ((float) $this->input('discount_percent', 0) > $cap) {
                $v->errors()->add('discount_percent', "Discount can't exceed the partner's effective discount ({$cap}%).");
            }
        });
    }
}
