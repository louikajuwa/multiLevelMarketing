<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewPartnerNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * MLM hierarchy operations.
 *
 * Tree model (v1): sponsor_id == parent_id (single edge). Each partner stores a
 * materialized `path` of ancestor ids ("/1/4/9/") and its `depth` (1..maxDepth).
 * Registration is transactional and row-locks the sponsor so concurrent signups
 * under the same sponsor keep counts correct.
 *
 *   path(child)  = path(parent) . parent.id . "/"
 *   depth(child) = depth(parent) + 1
 *   descendants  = partners where path LIKE parent.path . parent.id . "/%"
 */
class PartnerService
{
    /**
     * Register a brand-new partner (creates the user + partner row).
     *
     * @param  array{name:string,email:string,password:string,partner_level_id:int,sponsor_id?:int|null,discount_override_percent?:float|null}  $data
     */
    public function register(array $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            $sponsor = null;
            $path = '/';
            $depth = 1;

            if (! empty($data['sponsor_id'])) {
                // Lock the sponsor row for the duration of the transaction.
                $sponsor = Partner::whereKey($data['sponsor_id'])->lockForUpdate()->firstOrFail();

                $depth = $sponsor->depth + 1;
                $this->guardMaxDepth($depth);

                $path = $sponsor->path.$sponsor->id.'/';
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->assignRole(RoleName::Partner->value);

            $partner = Partner::create([
                'user_id' => $user->id,
                'partner_level_id' => $data['partner_level_id'],
                'discount_override_percent' => $data['discount_override_percent'] ?? null,
                'sponsor_id' => $sponsor?->id,
                'parent_id' => $sponsor?->id,
                'path' => $path,
                'depth' => $depth,
                'status' => 'active',
            ]);

            if ($sponsor) {
                $sponsor->increment('direct_referral_count');
                $this->incrementAncestorDownline($partner);
            }

            $partner->load(['user', 'level']);
            Notification::send(
                User::role([RoleName::SuperAdmin->value, RoleName::Admin->value])->get(),
                new NewPartnerNotification($partner),
            );

            return $partner;
        });
    }

    /** Update a partner's level / discount override / status (sponsor is immutable in v1). */
    public function update(Partner $partner, array $data): Partner
    {
        $partner->fill([
            'partner_level_id' => $data['partner_level_id'] ?? $partner->partner_level_id,
            'discount_override_percent' => $data['discount_override_percent'] ?? null,
            'status' => $data['status'] ?? $partner->status,
        ])->save();

        return $partner->refresh()->load(['user', 'level']);
    }

    /** The partner's effective buying discount: admin override wins, else level default. */
    public function effectiveDiscountPercent(Partner $partner): float
    {
        if ($partner->discount_override_percent !== null) {
            return (float) $partner->discount_override_percent;
        }

        return (float) ($partner->level?->discount_percent ?? 0);
    }

    /** Ancestor partner ids parsed from the materialized path ("/1/4/" => [1,4]). */
    public function ancestorIds(Partner $partner): array
    {
        return array_values(array_filter(explode('/', $partner->path)));
    }

    private function incrementAncestorDownline(Partner $partner): void
    {
        $ids = $this->ancestorIds($partner);
        if ($ids !== []) {
            Partner::whereIn('id', $ids)->increment('total_downline_count');
        }
    }

    private function guardMaxDepth(int $depth): void
    {
        $max = (int) (Setting::where('key', 'max_partner_depth')->value('value') ?: 4);

        if ($depth > $max) {
            throw ValidationException::withMessages([
                'sponsor_id' => "Maximum partner depth is {$max}. This sponsor is already at the deepest allowed level.",
            ]);
        }
    }
}
