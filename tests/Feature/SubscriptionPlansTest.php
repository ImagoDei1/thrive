<?php

declare(strict_types=1);

use App\Livewire\Admin\SubscriptionPlans;
use App\Models\SubscriberProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionActivationService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);
});

function planSubscriber(): SubscriberProfile
{
    // UserFactory sets email_verified_at, which the users table does not have.
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));

    return SubscriberProfile::create(['user_id' => $user->id]);
}

function pendingSubscription(SubscriberProfile $profile, string $slug): Subscription
{
    return Subscription::create([
        'subscriber_id' => $profile->id,
        'plan_id'       => SubscriptionPlan::where('slug', $slug)->value('id'),
        'status'        => 'pending',
    ]);
}

it('activates each package for its own duration', function (string $slug, int $hours) {
    $this->freezeSecond();
    $profile = planSubscriber();

    $sub = app(SubscriptionActivationService::class)->activate(pendingSubscription($profile, $slug));

    expect($sub->status)->toBe('active')
        ->and($sub->starts_at->equalTo(now()))->toBeTrue()
        ->and((int) $sub->starts_at->diffInHours($sub->ends_at))->toBe($hours)
        ->and($profile->fresh()->subscription_status)->toBe('active')
        ->and($profile->fresh()->expires_at->equalTo($sub->ends_at))->toBeTrue();
})->with([
    '24 hours' => ['daily', 24],
    '1 week'   => ['weekly', 24 * 7],
    '1 month'  => ['monthly', 24 * 30],
]);

it('stacks a new pass on top of time the subscriber already has', function () {
    $this->freezeSecond();
    $profile = planSubscriber();
    $service = app(SubscriptionActivationService::class);

    $week = $service->activate(pendingSubscription($profile, 'weekly'));
    $day  = $service->activate(pendingSubscription($profile, 'daily'));

    expect($day->starts_at->equalTo($week->ends_at))->toBeTrue()
        ->and($day->ends_at->equalTo(now()->addDays(8)))->toBeTrue()
        ->and($profile->fresh()->expires_at->equalTo($day->ends_at))->toBeTrue();
});

it('is idempotent when the payment webhook is delivered twice', function () {
    $profile = planSubscriber();
    $pending = pendingSubscription($profile, 'daily');
    $service = app(SubscriptionActivationService::class);

    $first  = $service->activate($pending);
    $second = $service->activate($pending);

    expect($second->ends_at->equalTo($first->ends_at))->toBeTrue();
});

it('expires a 24h pass on time but keeps the monthly grace period', function () {
    $profile = planSubscriber();
    $service = app(SubscriptionActivationService::class);
    $daily   = $service->activate(pendingSubscription($profile, 'daily'));

    $other   = planSubscriber();
    $monthly = $service->activate(pendingSubscription($other, 'monthly'));

    // 1 hour after the daily pass ends, 1 day into the monthly grace period
    $this->travelTo($monthly->ends_at->copy()->addDay());
    $daily->update(['ends_at' => now()->subHour()]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($daily->fresh()->status)->toBe('expired')
        ->and($profile->fresh()->subscription_status)->toBe('expired')
        ->and($monthly->fresh()->status)->toBe('active');

    $this->travel(3)->days();
    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($monthly->fresh()->status)->toBe('expired');
});

it('keeps the subscriber active while a stacked pass is still running', function () {
    $profile = planSubscriber();
    $service = app(SubscriptionActivationService::class);
    $day     = $service->activate(pendingSubscription($profile, 'daily'));
    $week    = $service->activate(pendingSubscription($profile, 'weekly'));

    $this->travelTo($day->ends_at->copy()->addHour());
    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($day->fresh()->status)->toBe('expired')
        ->and($week->fresh()->status)->toBe('active')
        ->and($profile->fresh()->subscription_status)->toBe('active');
});

it('only sends expiry reminders that fit inside the plan', function () {
    Notification::fake();
    $service = app(SubscriptionActivationService::class);

    $weekly  = planSubscriber();
    $monthly = planSubscriber();
    $service->activate(pendingSubscription($weekly, 'weekly'));
    $monthlySub = $service->activate(pendingSubscription($monthly, 'monthly'));

    // Day the weekly pass is 7 days from ending: no reminder for it
    $this->artisan('subscriptions:notify-expiring')->assertSuccessful();
    Notification::assertNothingSent();

    // Monthly 7 days before expiry: reminder
    $this->travelTo($monthlySub->ends_at->copy()->subDays(7));
    $this->artisan('subscriptions:notify-expiring')->assertSuccessful();
    Notification::assertSentTo($monthly->user, \App\Notifications\SubscriptionExpiringSoon::class);
});

it('lists every active package with its real interval', function () {
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $user->forceFill(['api_token' => 'plan-token'])->save();

    $response = $this->withToken('plan-token')->getJson('/api/plans');

    $response->assertOk();
    expect(collect($response->json('data'))->map(fn (array $plan) => Arr::only($plan, ['slug', 'interval', 'duration_days']))->all())->toBe([
        ['slug' => 'daily',   'interval' => 'day',   'duration_days' => 1],
        ['slug' => 'weekly',  'interval' => 'week',  'duration_days' => 7],
        ['slug' => 'monthly', 'interval' => 'month', 'duration_days' => 30],
    ]);
});

it('lets an admin edit plan prices and hide a plan', function () {
    $admin = User::create(Arr::except(User::factory()->admin()->raw(), ['email_verified_at', 'remember_token']));
    $daily = SubscriptionPlan::where('slug', 'daily')->first();

    Livewire::actingAs($admin)
        ->test(SubscriptionPlans::class)
        ->call('edit', $daily->id)
        ->set('price_mwk', '450')
        ->call('save')
        ->assertHasNoErrors()
        ->call('toggleActive', $daily->id);

    expect((float) $daily->fresh()->price_mwk)->toBe(450.0)
        ->and($daily->fresh()->is_active)->toBeFalse();
});
