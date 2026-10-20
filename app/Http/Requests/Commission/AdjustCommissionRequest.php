<?php

namespace App\Http\Requests\Commission;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000'],
            'note' => ['required', 'string', 'max:500'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(['partner_id', 'order_id']) || ! $this->filled('order_id')) {
                return;
            }
            $order = Order::find($this->input('order_id'));
            if ($order && (int) $order->partner_id !== (int) $this->input('partner_id')) {
                $v->errors()->add('order_id', 'The order does not belong to the selected partner.');
            }
        });
    }
}
