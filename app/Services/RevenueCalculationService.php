<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\ProviderPayout;
use App\Models\WatchTimeMonthly;

class RevenueCalculationService
{
    public function calculate(BillingPeriod $period): void
    {
        $allWatchTime = WatchTimeMonthly::with('channel')
            ->where('billing_period', $period->period_label)
            ->get();

        $totalSeconds = $allWatchTime->sum('total_seconds');

        if ($totalSeconds === 0) {
            return;
        }

        $period->update(['status' => 'calculating']);

        foreach ($allWatchTime as $channelWatch) {
            $sharePercent = $channelWatch->total_seconds / $totalSeconds;
            $amount       = round($period->provider_pool * $sharePercent, 2);

            ProviderPayout::updateOrCreate(
                [
                    'provider_id'       => $channelWatch->channel->provider_id,
                    'billing_period_id' => $period->id,
                ],
                [
                    'watch_seconds' => $channelWatch->total_seconds,
                    'watch_hours'   => round($channelWatch->total_seconds / 3600, 4),
                    'share_percent' => $sharePercent,
                    'amount_mwk'    => $amount,
                    'status'        => 'pending',
                ]
            );
        }
    }
}
