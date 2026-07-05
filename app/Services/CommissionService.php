<?php

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Setting;
use App\Notifications\CommissionEarnedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Commission rules (plan-confirmed):
 *   - direct   = commission_percent (earning partner's level, else Settings default) × order final subtotal
 *   - sponsor  = sponsor_bonus_percent (sponsor's level, else Settings default) × the direct commission
 *   - amounts rounded to the cent per row
 *   - generation is idempotent per order; reversal is a manual admin adjustment (negative row)
 */
class CommissionService
{
    /** Create direct + sponsor-bonus commissions for a freshly-paid order (idempotent). */
    public function generateForPaidOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if ($order->commissions()->whereIn('type', [CommissionType::Direct->value, CommissionType::SponsorBonus->value])->exists()) {
                return; // already generated
            }

            $order->loadMissing(['partner.level', 'partner.sponsor.level']);
            $partner = $order->partner;
            if (! $partner) {
                return;
            }

            $base = round((float) $order->subtotal, 2);
            $pct = (float) ($partner->level->commission_percent ?? $this->setting('default_commission_percent', 0));
            $directAmount = round($base * $pct / 100, 2);

            $direct = $order->commissions()->create([
                'partner_id' => $partner->id,
                'type' => CommissionType::Direct,
                'status' => CommissionStatus::Pending,
                'base_amount' => $base,
                'percent' => $pct,
                'amount' => $directAmount,
            ]);
            $partner->user?->notify(new CommissionEarnedNotification($direct));

            $sponsor = $partner->sponsor;
            if ($sponsor && $directAmount > 0) {
                $sbPct = (float) ($sponsor->level->sponsor_bonus_percent ?? $this->setting('default_sponsor_bonus_percent', 0));
                $bonus = round($directAmount * $sbPct / 100, 2);
                if ($bonus > 0) {
                    $bonusRow = $order->commissions()->create([
                        'partner_id' => $sponsor->id,
                        'type' => CommissionType::SponsorBonus,
                        'status' => CommissionStatus::Pending,
                        'base_amount' => $directAmount,
                        'percent' => $sbPct,
                        'amount' => $bonus,
                    ]);
                    $sponsor->user?->notify(new CommissionEarnedNotification($bonusRow));
                }
            }
        });
    }

    /** Manual admin adjustment / reversal (amount may be negative). */
    public function adjust(Partner $partner, float $amount, string $note, ?int $userId, ?Order $order = null): Commission
    {
        return Commission::create([
            'order_id' => $order?->id,
            'partner_id' => $partner->id,
            'type' => CommissionType::Adjustment,
            'status' => CommissionStatus::Paid,
            'base_amount' => 0,
            'percent' => 0,
            'amount' => round($amount, 2),
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    private function setting(string $key, mixed $default): mixed
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }
}
