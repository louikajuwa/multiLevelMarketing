<?php

namespace App\Http\Requests\Order;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'shipping_phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->has('customer_id')) {
                return;
            }
            $customer = Customer::with('partner')->find($this->input('customer_id'));
            if ($customer && ($customer->status !== 'active' || $customer->partner?->status !== 'active')) {
                $v->errors()->add('customer_id', 'Orders cannot be placed for an inactive customer or a customer of an inactive partner.');
            }
        });
    }
}
