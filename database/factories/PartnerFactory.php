<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'partner_level_id' => PartnerLevel::factory(),
            'discount_override_percent' => null,
            'sponsor_id' => null,
            'parent_id' => null,
            'path' => '/',
            'depth' => 1,
            'direct_referral_count' => 0,
            'total_downline_count' => 0,
            'status' => 'active',
        ];
    }
}
