<?php

declare(strict_types=1);

use App\Jobs\ConfirmPayChanguPaymentJob;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const AIRTEL_REF = '20be6c20-adeb-4b5b-a7ba-0769820df4fb';

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);

    $this->user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $this->user->forceFill(['api_token' => 'momo-token', 'name' => 'Chikondi Banda'])->save();
    $this->weekly = SubscriptionPlan::where('slug', 'weekly')->first();
});

function fakePayChangu(string $verifyStatus = 'success', ?float $verifyAmount = null): void
{
    Http::fake([
        'api.paychangu.com/mobile-money' => Http::response([
            'status' => 'success',
            'data'   => [
                ['id' => 1, 'name' => 'Airtel Money', 'ref_id' => AIRTEL_REF, 'short_code' => 'airtel'],
                ['id' => 2, 'name' => 'TNM Mpamba', 'ref_id' => '27494cb5-ba9e-437f-a114-4e7a7686bcca', 'short_code' => 'tnm'],
            ],
        ]),
        'api.paychangu.com/mobile-money/payments/initialize' => Http::response([
            'status' => 'success',
            'data'   => ['status' => 'pending'],
        ]),
        'api.paychangu.com/mobile-money/payments/*/verify' => fn (HttpRequest $request) => Http::response([
            'status' => 'success',
            'data'   => [
                'charge_id' => basename(dirname($request->url())),
                'status'    => $verifyStatus,
                'amount'    => $verifyAmount ?? (float) SubscriptionPlan::where('slug', 'weekly')->value('price_mwk'),
                'currency'  => 'MWK',
            ],
        ]),
    ]);
}

function startWeeklyCheckout(string $phone = '+265 991 234 567'): \Illuminate\Testing\TestResponse
{
    return test()->withToken('momo-token')->postJson('/api/subscription/checkout', [
        'plan_id'         => test()->weekly->id,
        'phone'           => $phone,
        'operator_ref_id' => AIRTEL_REF,
    ]);
}

function signedWebhook(array $payload, string $secret = 'paychangu-test-secret'): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload);

    return test()->call('POST', '/webhooks/paychangu', [], [], [], [
        'CONTENT_TYPE'   => 'application/json',
        'HTTP_SIGNATURE' => hash_hmac('sha256', $body, $secret),
    ], $body);
}

it('lists mobile money operators for the app', function () {
    fakePayChangu();

    $this->withToken('momo-token')->getJson('/api/payment-operators')
        ->assertOk()
        ->assertJsonPath('data.0.ref_id', AIRTEL_REF)
        ->assertJsonPath('data.1.short_code', 'tnm');
});

it('sends a PIN prompt and leaves a pending payment', function () {
    fakePayChangu();

    $response = startWeeklyCheckout()->assertStatus(202)->assertJsonPath('status', 'pending');

    $payment = Payment::where('paychangu_tx_id', $response->json('reference'))->firstOrFail();
    expect($payment->status)->toBe('pending')
        ->and($payment->phone_number)->toBe('0991234567')
        ->and($payment->subscription->status)->toBe('pending');

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/mobile-money/payments/initialize')
        && $r['mobile'] === '0991234567'
        && $r['mobile_money_operator_ref_id'] === AIRTEL_REF
        && $r['charge_id'] === $payment->paychangu_tx_id
        && (float) $r['amount'] === (float) $this->weekly->price_mwk
        && $r['first_name'] === 'Chikondi'
        && $r->hasHeader('Authorization', 'Bearer sec-test-key'));
});

it('rejects bad phone numbers and unknown operators before calling PayChangu', function () {
    fakePayChangu();

    startWeeklyCheckout('12345')->assertStatus(422)->assertJsonValidationErrors('phone');

    $this->withToken('momo-token')->postJson('/api/subscription/checkout', [
        'plan_id' => $this->weekly->id, 'phone' => '0991234567', 'operator_ref_id' => 'nope',
    ])->assertStatus(422)->assertJsonValidationErrors('operator_ref_id');

    Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'initialize'));
    expect(Payment::count())->toBe(0);
});

it('marks the payment failed when PayChangu refuses the charge', function () {
    Http::fake([
        'api.paychangu.com/mobile-money' => Http::response(['status' => 'success', 'data' => [
            ['id' => 1, 'name' => 'Airtel Money', 'ref_id' => AIRTEL_REF, 'short_code' => 'airtel'],
        ]]),
        'api.paychangu.com/mobile-money/payments/initialize' => Http::response([
            'status' => 'failed', 'message' => ['mobile' => ['The mobile is invalid.']], 'data' => null,
        ], 400),
    ]);

    startWeeklyCheckout()->assertStatus(422);

    $payment = Payment::firstOrFail();
    expect($payment->status)->toBe('failed')
        ->and($payment->subscription->status)->toBe('failed');
});

it('activates the subscription from a signed webhook after re-verifying', function () {
    fakePayChangu();
    $reference = startWeeklyCheckout()->json('reference');

    signedWebhook(['charge_id' => $reference, 'status' => 'success'])->assertOk();

    $payment = Payment::where('paychangu_tx_id', $reference)->firstOrFail();
    expect($payment->status)->toBe('completed')
        ->and($payment->subscription->status)->toBe('active')
        ->and((int) $payment->subscription->starts_at->diffInDays($payment->subscription->ends_at))->toBe(7);

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), "/mobile-money/payments/{$reference}/verify"));

    $this->withToken('momo-token')->getJson('/api/subscription')->assertJsonPath('status', 'active');
});

it('rejects webhooks with a bad signature', function () {
    Queue::fake();

    signedWebhook(['charge_id' => 'THR-X'], 'wrong-secret')->assertStatus(401);

    Queue::assertNothingPushed();
});

it('does not grant access when the webhook claims success but PayChangu says otherwise', function () {
    fakePayChangu(verifyStatus: 'failed');
    $reference = startWeeklyCheckout()->json('reference');

    signedWebhook(['charge_id' => $reference, 'status' => 'success'])->assertOk();

    $payment = Payment::where('paychangu_tx_id', $reference)->firstOrFail();
    expect($payment->status)->toBe('failed')
        ->and($payment->subscription->status)->toBe('failed');
});

it('refuses an underpaid charge', function () {
    fakePayChangu(verifyAmount: 1);
    $reference = startWeeklyCheckout()->json('reference');

    signedWebhook(['charge_id' => $reference])->assertOk();

    expect(Payment::where('paychangu_tx_id', $reference)->value('status'))->toBe('failed');
});

it('activates only once when the webhook is delivered twice', function () {
    fakePayChangu();
    $reference = startWeeklyCheckout()->json('reference');

    signedWebhook(['charge_id' => $reference])->assertOk();
    $endsAt = Payment::where('paychangu_tx_id', $reference)->firstOrFail()->subscription->ends_at;
    signedWebhook(['charge_id' => $reference])->assertOk();

    $payment = Payment::where('paychangu_tx_id', $reference)->firstOrFail();
    expect($payment->subscription->ends_at->equalTo($endsAt))->toBeTrue();
    Http::assertSentCount(3); // operators + initialize + one verify
});

it('settles the payment from the status poll when the webhook is late', function () {
    fakePayChangu();
    $reference = startWeeklyCheckout()->json('reference');

    $this->withToken('momo-token')->getJson("/api/subscription/checkout/{$reference}")
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('subscription.status', 'active');
});

it('keeps polling pending while the subscriber has not entered their PIN', function () {
    fakePayChangu(verifyStatus: 'pending');
    $reference = startWeeklyCheckout()->json('reference');

    $this->withToken('momo-token')->getJson("/api/subscription/checkout/{$reference}")
        ->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('subscription', null);
});

it('hides other users\' payments from the status poll', function () {
    fakePayChangu();
    $reference = startWeeklyCheckout()->json('reference');

    $other = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $other->forceFill(['api_token' => 'other-token'])->save();

    $this->withToken('other-token')->getJson("/api/subscription/checkout/{$reference}")->assertNotFound();
});

it('re-checks a pending charge from the queue until it settles', function () {
    fakePayChangu(verifyStatus: 'pending');
    $reference = startWeeklyCheckout()->json('reference');
    $payment   = Payment::where('paychangu_tx_id', $reference)->firstOrFail();

    $job = (new ConfirmPayChanguPaymentJob($payment->id))->withFakeQueueInteractions();
    $job->handle(app(\App\Services\SubscriptionPaymentService::class));

    $job->assertReleased();
});
