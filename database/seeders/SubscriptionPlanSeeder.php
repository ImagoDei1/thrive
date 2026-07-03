<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'         => 'Monthly',
                'slug'         => 'monthly',
                'description'  => 'Full access to all channels for one month.',
                'price_mwk'    => 3500.00,
                'duration_days' => 30,
                'is_active'    => true,
                'features'     => ['Unlimited streaming', 'All channels', 'HD quality'],
            ],
            [
                'name'         => 'Quarterly',
                'slug'         => 'quarterly',
                'description'  => 'Full access for three months at a discounted rate.',
                'price_mwk'    => 9500.00,
                'duration_days' => 90,
                'is_active'    => true,
                'features'     => ['Unlimited streaming', 'All channels', 'HD quality', '10% discount'],
            ],
            [
                'name'         => 'Annual',
                'slug'         => 'annual',
                'description'  => 'Best value — full access for a full year.',
                'price_mwk'    => 35000.00,
                'duration_days' => 365,
                'is_active'    => true,
                'features'     => ['Unlimited streaming', 'All channels', 'HD quality', '17% discount', 'Priority support'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
