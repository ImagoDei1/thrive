<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\ProviderPayout;
use App\Models\WatchTimeMonthly;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Provider Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $provider = auth()->user()->providerProfile;
        $currentPeriod = now()->format('Y-m');

        $channelIds = $provider->channels()->pluck('id');

        $watchThisMonth = WatchTimeMonthly::whereIn('channel_id', $channelIds)
            ->where('billing_period', $currentPeriod)
            ->sum('total_seconds');

        $estimatedPayout = ProviderPayout::where('provider_id', $provider->id)
            ->whereHas('billingPeriod', fn ($q) => $q->where('period_label', $currentPeriod))
            ->value('amount_mwk') ?? 0;

        $lastPayout = ProviderPayout::where('provider_id', $provider->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        $channel = $provider->channels()->withCount('contentItems')->first();

        return view('livewire.provider.dashboard', [
            'provider'        => $provider,
            'watchThisMonth'  => $watchThisMonth,
            'estimatedPayout' => $estimatedPayout,
            'lastPayout'      => $lastPayout,
            'channel'         => $channel,
        ])->layout('components.provider-layout');
    }
}
