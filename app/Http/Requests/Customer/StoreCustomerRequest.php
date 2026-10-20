<?php

namespace App\Http\Requests\Customer;

use App\Models\Partner;
use App\Services\PartnerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Partners can only create customers under themselves — force their own partner_id. */
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user && ! $user->isStaff() && $user->partner) {
            $this->merge(['partner_id' => $user->partner->id]);
        }
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
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $partnerId = (int) $this->input('partner_id');
            if (! $partnerId) {
                return;
            }
            $partner = Partner::with('level')->find($partnerId);
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
