<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        StoreSetting::firstOrCreate([], [
            'store_name' => 'StylePOS Store',
            'reporting_currency' => 'USD',
            'khr_per_usd' => 4000, // PLACEHOLDER: confirm the shop's rate and update policy
            'timezone' => 'Asia/Phnom_Penh',
            'default_locale' => 'en',
            'receipt_footer' => 'Thank you!',
        ]);
    }
}
