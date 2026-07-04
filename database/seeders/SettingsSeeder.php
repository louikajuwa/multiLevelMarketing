<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company_name' => 'MLM Enterprise',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'tax_percent' => '0',
            'default_partner_discount' => '5',
            'default_commission_percent' => '10',
            'default_sponsor_bonus_percent' => '5',
            'max_partner_depth' => '4',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_encrypted' => false]);
        }
    }
}
