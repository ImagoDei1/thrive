<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\ProviderPayout;
use App\Models\WatchTimeMonthly;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Payout History')]
class PayoutHistory extends Component
{
    use WithPagination;

    public array $headers = [
        ['key' => 'period',        'label' => 'Period'],
        ['key' => 'watch_hours',   'label' => 'Watch Hours'],
        ['key' => 'share_percent', 'label' => 'Share %'],
        ['key' => 'gross_amount_mwk', 'label' => 'Earned (MWK)'],
        ['key' => 'setup_fee_mwk', 'label' => 'Setup Fee'],
        ['key' => 'amount_mwk',   'label' => 'Payout (MWK)'],
        ['key' => 'status',        'label' => 'Status'],
        ['key' => 'paid_at',       'label' => 'Paid On'],
    ];

    public function render()
    {
        $provider = auth()->user()->providerProfile;
        $channelIds = $provider->channels()->pluck('id');

        $payouts = ProviderPayout::with('billingPeriod')
            ->where('provider_id', $provider->id)
            ->latest('created_at')
            ->paginate(15);

        // Current month watch time summary per channel
        $currentPeriod = now()->format('Y-m');
        $watchSummary = WatchTimeMonthly::whereIn('channel_id', $channelIds)
            ->where('billing_period', $currentPeriod)
            ->with('channel')
            ->get();

        return view('livewire.provider.payout-history', [
            'payouts'      => $payouts,
            'watchSummary' => $watchSummary,
        ])->layout('components.provider-layout');
    }
}
