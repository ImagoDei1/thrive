<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Application Pending')]
class Onboarding extends Component
{
    public function mount(): void
    {
        $provider = auth()->user()->providerProfile;

        if ($provider?->is_approved) {
            $this->redirect(route('provider.dashboard'), navigate: true);
        }
    }

    public function render()
    {
        $provider = auth()->user()->providerProfile;

        return view('livewire.provider.onboarding', [
            'provider' => $provider,
        ])->layout('components.layouts.auth.thrive');
    }
}
