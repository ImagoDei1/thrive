<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\WatchTimeMonthly;

class RevenueCalculationService
{
    public function calculate(BillingPeriod $period): void
    {
        $allWatchTime = WatchTimeMonthly::with(['channel' => fn ($q) => $q->withTrashed()])
            ->where('billing_period', $period->period_label)
            ->get();

        $totalSeconds = (int) $allWatchTime->sum('total_seconds');

        if ($totalSeconds === 0) {
            return;
        }

        $period->update(['status' => 'calculating']);

        // Payouts are per provider, so a provider's channels are summed together.
        $secondsByProvider = $allWatchTime
            ->groupBy(fn (WatchTimeMonthly $row) => $row->channel->provider_id)
            ->map(fn ($rows) => (int) $rows->sum('total_seconds'));

        $providers = ProviderProfile::withTrashed()
            ->whereIn('id', $secondsByProvider->keys())
            ->get()
            ->keyBy('id');

        // Share of attributable revenue a provider normally receives, e.g. 80
        $providerPercentage = 100 - (float) $period->platform_percentage;
        $totalSetupFees     = 0.0;

        foreach ($secondsByProvider as $providerId => $seconds) {
            $sharePercent = $seconds / $totalSeconds;
            $gross        = round($period->provider_pool * $sharePercent, 2);

            // Setup fee: e.g. 10 points of an 80% share → provider keeps 70/80 of gross.
            $setupFeePercentage = min($providers[$providerId]->setupFeePercentageFor($period->period_label), $providerPercentage);
            $setupFee           = $providerPercentage > 0 ? round($gross * $setupFeePercentage / $providerPercentage, 2) : 0.0;

            ProviderPayout::updateOrCreate(
                [
                    'provider_id'       => $providerId,
                    'billing_period_id' => $period->id,
                ],
                [
                    'watch_seconds'        => $seconds,
                    'watch_hours'          => round($seconds / 3600, 4),
                    'share_percent'        => $sharePercent,
                    'gross_amount_mwk'     => $gross,
                    'setup_fee_percentage' => $setupFeePercentage,
                    'setup_fee_mwk'        => $setupFee,
                    'amount_mwk'           => round($gross - $setupFee, 2),
                    'status'               => 'pending',
                ]
            );

            $totalSetupFees += $setupFee;
        }

        $period->update(['setup_fees_mwk' => round($totalSetupFees, 2)]);
    }
}
