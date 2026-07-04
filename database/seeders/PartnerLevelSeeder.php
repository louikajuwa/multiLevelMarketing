<?php

namespace Database\Seeders;

use App\Models\PartnerLevel;
use Illuminate\Database\Seeder;

class PartnerLevelSeeder extends Seeder
{
    public function run(): void
    {
        // [level, name, discount%, commission%, sponsor_bonus%]
        $levels = [
            [1, 'Level 1', 5.00, 8.00, 5.00],
            [2, 'Level 2', 10.00, 10.00, 5.00],
            [3, 'Level 3', 15.00, 12.00, 5.00],
            [4, 'Level 4', 20.00, 15.00, 5.00],
        ];

        foreach ($levels as [$level, $name, $discount, $commission, $bonus]) {
            PartnerLevel::updateOrCreate(
                ['level' => $level],
                [
                    'name' => $name,
                    'discount_percent' => $discount,
                    'commission_percent' => $commission,
                    'sponsor_bonus_percent' => $bonus,
                    'is_active' => true,
                ],
            );
        }
    }
}
