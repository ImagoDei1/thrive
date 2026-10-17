<?php

declare(strict_types=1);

namespace App\Services\PayChangu;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP wrapper over the PayChangu direct mobile-money API.
 * Domain code should go through SubscriptionPaymentService.
 */
class PayChanguClient
{
    /**
     * Supported operators, e.g. [['ref_id' => '...', 'name' => 'Airtel Money', 'short_code' => 'airtel']].
     *
     * @return list<array{ref_id: string, name: string, short_code: string}>
     */
    public function operators(): array
    {
        return Cache::remember('paychangu:operators', now()->addHours(6), function () {
            return collect($this->request()->get('/mobile-money')->throw()->json('data', []))
                ->map(fn (array $op) => [
                    'ref_id'     => (string) $op['ref_id'],
                    'name'       => (string) $op['name'],
                    'short_code' => (string) ($op['short_code'] ?? ''),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Push a USSD/PIN prompt to the subscriber's phone. The result arrives later
     * via webhook or verifyCharge(); a successful response only means "prompt sent".
     *
     * @param  array{email?: string|null, first_name?: string|null, last_name?: string|null}  $customer
     * @return array<string, mixed>
     */
    public function chargeMobileMoney(string $mobile, string $operatorRefId, float $amount, string $chargeId, array $customer = []): array
    {
        $response = $this->request()->post('/mobile-money/payments/initialize', array_filter([
            'mobile'                       => $mobile,
            'mobile_money_operator_ref_id' => $operatorRefId,
            'amount'                       => $amount,
            'charge_id'                    => $chargeId,
            'email'                        => $customer['email'] ?? null,
            'first_name'                   => $customer['first_name'] ?? null,
            'last_name'                    => $customer['last_name'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        return $this->unwrap($response, 'initialize charge');
    }

    /** @return array<string, mixed> The charge record (status, amount, currency, ...). */
    public function verifyCharge(string $chargeId): array
    {
        $response = $this->request()->get('/mobile-money/payments/' . rawurlencode($chargeId) . '/verify');

        return $this->unwrap($response, 'verify charge');
    }

    /** @return array<string, mixed> */
    private function unwrap(Response $response, string $action): array
    {
        if ($response->failed() || $response->json('status') === 'failed') {
            throw new PayChanguException(
                "PayChangu {$action} failed: " . $this->errorMessage($response),
                $response->status(),
                $response->json() ?? [],
            );
        }

        $data = $response->json('data');

        // Some PayChangu responses nest the charge under data.transaction.
        return is_array($data) ? ($data['transaction'] ?? $data) : [];
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('message');

        // Validation errors come back as {"message": {"field": ["..."]}}
        if (is_array($message)) {
            return collect($message)->flatten()->implode(' ');
        }

        return (string) ($message ?: "HTTP {$response->status()}");
    }

    private function request(): PendingRequest
    {
        $key = (string) config('services.paychangu.secret_key');

        if ($key === '') {
            throw new RuntimeException('PAYCHANGU_SECRET_KEY is not configured.');
        }

        return Http::baseUrl((string) config('services.paychangu.api_base'))
            ->withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
