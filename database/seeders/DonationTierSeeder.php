<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use STS\Models\DonationTier;

class DonationTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'slug' => 'cafe',
                'label_key' => 'donationTierCafe',
                'amount_cents' => 500000,
                'icon' => 'fa-coffee',
                'sort_order' => 1,
            ],
            [
                'slug' => 'beer',
                'label_key' => 'donationTierBeer',
                'amount_cents' => 750000,
                'icon' => 'fa-beer',
                'sort_order' => 2,
            ],
            [
                'slug' => 'food',
                'label_key' => 'donationTierFood',
                'amount_cents' => 1200000,
                'icon' => 'fa-cutlery',
                'sort_order' => 3,
            ],
        ];

        foreach ($tiers as $tier) {
            DonationTier::query()->updateOrCreate(
                ['slug' => $tier['slug']],
                array_merge($tier, ['is_active' => true])
            );
        }
    }
}
