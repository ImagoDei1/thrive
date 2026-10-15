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
                'name'          => '24-Hour Pass',
                'slug'          => 'daily',
                'description'   => 'Full access to all channels for 24 hours — ideal for a single event or film.',
                'price_mwk'     => 300.00,
                'duration_days' => 1,
                'grace_days'    => 0,
                'is_active'     => true,
                'features'      => ['Unlimited streaming for 24 hours', 'All channels', 'HD quality'],
            ],
            [
                'name'          => 'Weekly',
                'slug'          => 'weekly',
                'description'   => 'Full access to all channels for 7 days — binge a series.',
                'price_mwk'     => 1200.00,
                'duration_days' => 7,
                'grace_days'    => 0,
                'is_active'     => true,
                'features'      => ['Unlimited streaming for 7 days', 'All channels', 'HD quality'],
            ],
            [
                'name'          => 'Monthly',
                'slug'          => 'monthly',
                'description'   => 'Full access to all channels for one month.',
                'price_mwk'     => 3500.00,
                'duration_days' => 30,
                'grace_days'    => 3,
                'is_active'     => true,
                'features'      => ['Unlimited streaming', 'All channels', 'HD quality'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
