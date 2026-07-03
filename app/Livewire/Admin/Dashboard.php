<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\BillingPeriod;
use App\Models\Channel;
use App\Models\ProviderPayout;
use App\Models\SubscriberProfile;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $currentPeriod = now()->format('Y-m');

        return view('livewire.admin.dashboard', [
            'totalSubscribers'  => SubscriberProfile::where('subscription_status', 'active')->count(),
            'activeChannels'    => Channel::where('is_active', true)->count(),
            'pendingPayouts'    => ProviderPayout::where('status', 'pending')->count(),
            'currentRevenue'    => BillingPeriod::where('period_label', $currentPeriod)->value('total_revenue') ?? 0,
            'recentProviders'   => \App\Models\ProviderProfile::with('user')
                                    ->where('is_approved', false)
                                    ->latest()
                                    ->take(5)
                                    ->get(),
        ])->layout('components.admin-layout');
    }
}
