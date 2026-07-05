<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;

/**
 * Resolves the layered discount and computes per-line money.
 *
 *   effective_customer_discount% = min(
 *       customer.discount_percent,
 *       partner effective discount (override ?? level default),
 *       product.partner_discount (if set)
 *   )
 *   unit_final = round(retail * (1 - eff/100), 2)   // round each line to the cent
 */
class PricingService
{
    public function __construct(private readonly PartnerService $partners) {}

    /** Effective discount % applied to a customer buying a given product. */
    public function effectiveDiscountPercent(Customer $customer, Product $product): float
    {
        $partnerCap = $this->partners->effectiveDiscountPercent($customer->partner);
        $eff = min((float) $customer->discount_percent, $partnerCap);

        if ($product->partner_discount !== null) {
            $eff = min($eff, (float) $product->partner_discount);
        }

        return round(max($eff, 0.0), 2);
    }

    /**
     * Price a single line. Returns cents-accurate values (rounded per line).
     *
     * @return array{unit_retail_price:float,unit_discount:float,unit_final_price:float,line_total:float,discount_percent:float}
     */
    public function priceLine(Customer $customer, Product $product, int $quantity): array
    {
        $retail = round((float) $product->retail_price, 2);
        $pct = $this->effectiveDiscountPercent($customer, $product);
        $final = round($retail * (1 - $pct / 100), 2);
        $discount = round($retail - $final, 2);

        return [
            'discount_percent' => $pct,
            'unit_retail_price' => $retail,
            'unit_discount' => $discount,
            'unit_final_price' => $final,
            'line_total' => round($final * $quantity, 2),
        ];
    }
}
