<?php

declare(strict_types=1);

use App\Livewire\Admin\ProviderShow;
use App\Models\BillingPeriod;
use App\Models\Channel;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\WatchTimeMonthly;
use App\Notifications\ProviderKycApproved;
use App\Services\ProviderSetupFeeService;
use App\Services\RevenueCalculationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function feeUser(string $role): User
{
    // UserFactory sets email_verified_at, which the users table does not have.
    return User::create(Arr::except(User::factory()->{$role}()->raw(), ['email_verified_at', 'remember_token']));
}

function feeProvider(string $name, array $attrs = []): ProviderProfile
{
    return ProviderProfile::create(array_merge([
        'user_id'      => feeUser('provider')->id,
        'company_name' => $name,
        'is_approved'  => true,
        'kyc_status'   => 'approved',
    ], $attrs));
}

function feeChannelWatch(ProviderProfile $provider, string $period, int $seconds): Channel
{
    $channel = Channel::create([
        'provider_id' => $provider->id,
        'name'        => $provider->company_name . ' TV',
        'slug'        => 'ch-' . Str::random(8),
        'rtmp_key'    => Str::random(32),
    ]);

    WatchTimeMonthly::create([
        'channel_id'     => $channel->id,
        'billing_period' => $period,
        'total_seconds'  => $seconds,
        'total_hours'    => round($seconds / 3600, 4),
    ]);

    return $channel;
}

function feePeriod(string $label, float $revenue): BillingPeriod
{
    return BillingPeriod::create([
        'period_label'        => $label,
        'starts_at'           => "{$label}-01",
        'ends_at'             => now()->parse("{$label}-01")->endOfMonth()->toDateString(),
        'total_revenue'       => $revenue,
        'platform_share'      => $revenue * 0.2,
        'provider_pool'       => $revenue * 0.8,
        'platform_percentage' => 20,
        'status'              => 'open',
    ]);
}

beforeEach(function () {
    Notification::fake();
    Queue::fake(); // Approval dispatches ProvisionBunnyLibraryJob.
});

it('withholds the setup fee from a new provider and pays established providers in full', function () {
    // MWK 100,000 revenue → 80,000 pool. Equal watch time → 40,000 gross each.
    $period      = feePeriod('2026-11', 100_000);
    $newcomer    = feeProvider('New Church');
    $established = feeProvider('Old Church');
    app(ProviderSetupFeeService::class)->assign($newcomer, 10, '2026-10', 6);

    feeChannelWatch($newcomer, '2026-11', 3600);
    feeChannelWatch($established, '2026-11', 3600);

    app(RevenueCalculationService::class)->calculate($period);

    $newPayout = ProviderPayout::where('provider_id', $newcomer->id)->first();
    $oldPayout = ProviderPayout::where('provider_id', $established->id)->first();

    // Provider gets 70% instead of 80% of their 50,000 attributable revenue: 35,000
    expect((float) $newPayout->gross_amount_mwk)->toBe(40000.0)
        ->and((float) $newPayout->setup_fee_percentage)->toBe(10.0)
        ->and((float) $newPayout->setup_fee_mwk)->toBe(5000.0)
        ->and((float) $newPayout->amount_mwk)->toBe(35000.0)
        ->and((float) $oldPayout->amount_mwk)->toBe(40000.0)
        ->and((float) $oldPayout->setup_fee_mwk)->toBe(0.0)
        ->and((float) $period->fresh()->setup_fees_mwk)->toBe(5000.0);
});

it('stops withholding once the setup-fee months are over', function () {
    $provider = feeProvider('Grace Church');
    app(ProviderSetupFeeService::class)->assign($provider, 10, '2026-01', 6); // Jan–Jun 2026

    expect($provider->setup_fee_ends_period)->toBe('2026-06')
        ->and($provider->setupFeePercentageFor('2025-12'))->toBe(0.0)
        ->and($provider->setupFeePercentageFor('2026-01'))->toBe(10.0)
        ->and($provider->setupFeePercentageFor('2026-06'))->toBe(10.0)
        ->and($provider->setupFeePercentageFor('2026-07'))->toBe(0.0);

    $period = feePeriod('2026-07', 10_000);
    feeChannelWatch($provider, '2026-07', 600);
    app(RevenueCalculationService::class)->calculate($period);

    expect((float) ProviderPayout::where('provider_id', $provider->id)->value('amount_mwk'))->toBe(8000.0);
});

it('sums all of a provider\'s channels into one payout', function () {
    $period   = feePeriod('2026-11', 10_000);
    $multi    = feeProvider('Two Channels');
    $single   = feeProvider('One Channel');
    feeChannelWatch($multi, '2026-11', 1000);
    feeChannelWatch($multi, '2026-11', 2000);
    feeChannelWatch($single, '2026-11', 1000);

    app(RevenueCalculationService::class)->calculate($period);

    expect((float) ProviderPayout::where('provider_id', $multi->id)->value('amount_mwk'))->toBe(6000.0)
        ->and((float) ProviderPayout::where('provider_id', $single->id)->value('amount_mwk'))->toBe(2000.0);
});

it('never pays a provider with zero watch time, setup fee or not', function () {
    $period  = feePeriod('2026-11', 10_000);
    $watched = feeProvider('Watched');
    $idle    = feeProvider('Idle');
    app(ProviderSetupFeeService::class)->assign($idle, 10, '2026-11', 3);
    feeChannelWatch($watched, '2026-11', 100);

    app(RevenueCalculationService::class)->calculate($period);

    expect(ProviderPayout::where('provider_id', $idle->id)->exists())->toBeFalse();
});

it('applies default terms from the approval month when KYC is approved', function () {
    $this->travelTo('2026-10-15');
    $provider = feeProvider('Approved Church', [
        'is_approved'      => false,
        'kyc_status'       => 'submitted',
        'kyc_submitted_at' => now(),
    ]);

    Livewire::actingAs(feeUser('admin'))
        ->test(ProviderShow::class, ['provider' => $provider])
        ->call('approve');

    $provider->refresh();

    expect((float) $provider->setup_fee_percentage)->toBe(10.0)
        ->and($provider->setup_fee_starts_period)->toBe('2026-10')
        ->and($provider->setup_fee_ends_period)->toBe('2027-03')
        ->and($provider->setupFeeStatus())->toBe('active');

    Notification::assertSentTo($provider->user, ProviderKycApproved::class);
});

it('lets an admin change the terms but never beyond 6 months', function () {
    $provider = feeProvider('Grace Church');
    $admin    = feeUser('admin');

    Livewire::actingAs($admin)
        ->test(ProviderShow::class, ['provider' => $provider])
        ->call('openSetupFee')
        ->set('setupFeePercentage', '10')
        ->set('setupFeeStartsPeriod', '2026-10')
        ->set('setupFeeMonths', 7)
        ->call('saveSetupFee')
        ->assertHasErrors(['setupFeeMonths' => 'max'])
        ->set('setupFeeMonths', 3)
        ->set('setupFeePercentage', '12.5')
        ->call('saveSetupFee')
        ->assertHasNoErrors();

    $provider->refresh();
    expect((float) $provider->setup_fee_percentage)->toBe(12.5)
        ->and($provider->setup_fee_ends_period)->toBe('2026-12');

    // 0 months removes the setup fee entirely
    Livewire::actingAs($admin)
        ->test(ProviderShow::class, ['provider' => $provider])
        ->set('setupFeeMonths', 0)
        ->call('saveSetupFee')
        ->assertHasNoErrors();

    expect($provider->fresh()->setupFeeStatus())->toBe('none');
});

it('rejects terms longer than 6 months at the service level too', function () {
    app(ProviderSetupFeeService::class)->assign(feeProvider('Grace Church'), 10, '2026-10', 7);
})->throws(InvalidArgumentException::class);
