<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriberProfile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);

        $admin = User::create([
            'name'     => 'Platform Admin',
            'email'    => 'admin@Thrive.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'phone'    => '+265991000001',
            'is_active' => true,
        ]);

        $providers = [
            ['name' => 'Grace Church Media',   'email' => 'grace@Thrive.test',   'company' => 'Grace Church Media'],
            ['name' => 'Malawi Broadcasting',  'email' => 'mbc@Thrive.test',     'company' => 'Malawi Broadcasting Corp'],
            ['name' => 'Edu Vision Africa',    'email' => 'edu@Thrive.test',     'company' => 'Edu Vision Africa'],
        ];

        foreach ($providers as $i => $data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make('password'),
                'role'      => 'provider',
                'phone'     => '+26599200000' . ($i + 1),
                'is_active' => true,
            ]);

            $profile = ProviderProfile::create([
                'user_id'          => $user->id,
                'company_name'     => $data['company'],
                'contact_person'   => $data['name'],
                'setup_fee_status' => 'paid',
                'setup_fee_amount' => 50000.00,
                'is_approved'      => true,
                'onboarded_at'     => now(),
            ]);

            Channel::create([
                'provider_id'      => $profile->id,
                'name'             => $data['company'],
                'slug'             => Str::slug($data['company']),
                'category'         => match ($i) { 0 => 'religion', 1 => 'news', default => 'education' },
                'language'         => 'en',
                'is_active'        => true,
                'rtmp_key'         => Str::random(32),
                'rtmp_ingest_url'  => 'rtmp://stream.Thrive.test/live',
                'hls_playback_url' => 'https://stream.Thrive.test/hls/' . Str::slug($data['company']) . '.m3u8',
            ]);
        }

        $subscribers = [
            ['name' => 'Alice Banda',   'email' => 'alice@Thrive.test'],
            ['name' => 'Bob Chirwa',    'email' => 'bob@Thrive.test'],
            ['name' => 'Carol Phiri',   'email' => 'carol@Thrive.test'],
            ['name' => 'David Mwale',   'email' => 'david@Thrive.test'],
            ['name' => 'Eve Tembo',     'email' => 'eve@Thrive.test'],
        ];

        $monthlyPlan = SubscriptionPlan::where('slug', 'monthly')->first();

        foreach ($subscribers as $i => $data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make('password'),
                'role'      => 'subscriber',
                'phone'     => '+26599300000' . ($i + 1),
                'is_active' => true,
            ]);

            $startsAt  = now()->subDays(rand(1, 20));
            $expiresAt = $startsAt->copy()->addDays(30);

            $profile = SubscriberProfile::create([
                'user_id'             => $user->id,
                'subscription_status' => 'active',
                'subscribed_at'       => $startsAt,
                'expires_at'          => $expiresAt,
            ]);

            if ($monthlyPlan) {
                Subscription::create([
                    'subscriber_id' => $profile->id,
                    'plan_id'       => $monthlyPlan->id,
                    'status'        => 'active',
                    'starts_at'     => $startsAt,
                    'ends_at'       => $expiresAt,
                ]);
            }
        }
    }
}
