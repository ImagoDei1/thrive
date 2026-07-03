<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (auth()->check()) {
        return match(auth()->user()->role) {
            'admin'      => redirect()->route('admin.dashboard'),
            'provider'   => auth()->user()->providerProfile?->is_approved
                                ? redirect()->route('provider.dashboard')
                                : redirect()->route('provider.onboarding'),
            'subscriber' => redirect()->route('subscriber.dashboard'),
            default      => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard',              \App\Livewire\Admin\Dashboard::class)->name('dashboard');
    Route::get('providers',              \App\Livewire\Admin\Providers::class)->name('providers.index');
    Route::get('providers/{provider}',   \App\Livewire\Admin\ProviderShow::class)->name('providers.show');
    Route::get('channels',               \App\Livewire\Admin\Channels::class)->name('channels.index');
    Route::get('change-requests',        \App\Livewire\Admin\ChangeRequests::class)->name('change-requests.index');
    Route::get('billing',                \App\Livewire\Admin\BillingPeriods::class)->name('billing.index');
    Route::get('payouts',                \App\Livewire\Admin\Payouts::class)->name('payouts.index');
    Route::get('subscribers',            \App\Livewire\Admin\Subscribers::class)->name('subscribers.index');
});

// Provider registration (guest only)
Route::middleware('guest')->prefix('provider')->name('provider.')->group(function () {
    Route::get('register', \App\Livewire\Provider\Register::class)->name('register');
});

// Onboarding holding page (authenticated provider, not yet approved)
Route::middleware(['auth', 'role:provider'])->prefix('provider')->name('provider.')->group(function () {
    Route::get('onboarding', \App\Livewire\Provider\Onboarding::class)->name('onboarding');
});

// Provider portal (authenticated + approved)
Route::middleware(['auth', 'role:provider', 'provider.approved'])->prefix('provider')->name('provider.')->group(function () {
    Route::get('dashboard',                        \App\Livewire\Provider\Dashboard::class)->name('dashboard');
    Route::get('channels/create',                  \App\Livewire\Provider\ChannelCreate::class)->name('channels.create');
    Route::get('channels/{channel}',               \App\Livewire\Provider\ChannelInfo::class)->name('channels.show');
    Route::get('channels/{channel}/setup',         \App\Livewire\Provider\ChannelSetup::class)->name('channels.setup');
    Route::get('channels/{channel}/content',       \App\Livewire\Provider\ContentItems::class)->name('content.index');
    Route::get('payouts',                          \App\Livewire\Provider\PayoutHistory::class)->name('payouts.index');
});

Route::middleware(['auth', 'role:subscriber'])->prefix('subscriber')->name('subscriber.')->group(function () {
    Route::get('dashboard', \App\Livewire\Subscriber\Dashboard::class)->name('dashboard');
    Route::get('billing',   \App\Livewire\Subscriber\BillingHistory::class)->name('billing');
});

require __DIR__.'/auth.php';
