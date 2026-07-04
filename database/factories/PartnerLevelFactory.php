<?php

namespace Database\Factories;

use App\Models\PartnerLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerLevelFactory extends Factory
{
    protected $model = PartnerLevel::class;

    public function definition(): array
    {
        static $level = 0;
        $level++;

        return [
            'name' => "Level {$level}",
            'level' => $level,
            'discount_percent' => $level * 5,
            'commission_percent' => 5 + $level,
            'sponsor_bonus_percent' => 2,
            'is_active' => true,
        ];
    }
}
