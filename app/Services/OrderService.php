<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CommissionService $commissions,
    ) {}

    /**
     * Place an order for a customer. Atomic: locks each product row, checks + decrements
     * stock, captures per-line pricing, writes order + items + a pending payment.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $lines
     */
    public function place(Customer $customer, array $lines, array $shipping = []): Order
    {
        return DB::transaction(function () use ($customer, $lines, $shipping): Order {
            $customer->loadMissing(['partner.level', 'user']);

            $order = Order::create([
                'order_number' => $this->number(),
                'customer_id' => $customer->id,
                'partner_id' => $customer->partner_id,
                'status' => OrderStatus::Pending,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
                'shipping_name' => $shipping['shipping_name'] ?? $customer->user->name,
                'shipping_address' => $shipping['shipping_address'] ?? $customer->address,
                'shipping_phone' => $shipping['shipping_phone'] ?? $customer->phone,
                'placed_at' => now(),
            ]);

            $subtotal = 0.0;
            $discountTotal = 0.0;

            foreach ($lines as $line) {
                $qty = max(1, (int) $line['quantity']);
                // Lock the product row so concurrent orders can't oversell.
                $product = Product::whereKey($line['product_id'])->lockForUpdate()->firstOrFail();

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$product->name} (have {$product->stock}, need {$qty}).",
                    ]);
                }

                $priced = $this->pricing->priceLine($customer, $product, $qty);
                $product->decrement('stock', $qty);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_retail_price' => $priced['unit_retail_price'],
                    'unit_discount' => $priced['unit_discount'],
                    'unit_final_price' => $priced['unit_final_price'],
                    'line_total' => $priced['line_total'],
                ]);

                $subtotal += $priced['line_total'];
                $discountTotal += $priced['unit_discount'] * $qty;
            }

            $subtotal = round($subtotal, 2);
            $order->update([
                'subtotal' => $subtotal,
                'discount_total' => round($discountTotal, 2),
                'tax_total' => 0,
                'grand_total' => $subtotal,
            ]);

            $order->payments()->create([
                'amount' => $subtotal,
                'method' => 'manual',
                'status' => PaymentStatus::Pending,
            ]);

            return $order->load(['items', 'customer.user', 'partner.user', 'payments']);
        });
    }

    /** Change order status. Marking Paid settles the manual payment (commission hook: phase 12). */
    public function setStatus(Order $order, OrderStatus $status): Order
    {
        return DB::transaction(function () use ($order, $status): Order {
            $order->update(['status' => $status]);

            if ($status === OrderStatus::Paid) {
                $order->payments()->latest('id')->first()?->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);
                // Commission base = final subtotal (after discount); idempotent.
                $this->commissions->generateForPaidOrder($order);
            }

            return $order->refresh();
        });
    }

    private function number(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
