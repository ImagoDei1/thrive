<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.thrive')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $user = Auth::user();
        $default = match($user->role) {
            'admin'    => route('admin.dashboard', absolute: false),
            'provider' => $user->providerProfile?->is_approved
                            ? route('provider.dashboard', absolute: false)
                            : route('provider.onboarding', absolute: false),
            default    => route('home', absolute: false),
        };

        $this->redirectIntended(default: $default, navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-8">

    {{-- Heading --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
        <p class="text-sm text-base-content/50 mt-1">Sign in to your Thrive account</p>
    </div>

    {{-- Session status --}}
    @if (session('status'))
        <div class="alert alert-success text-sm py-2">{{ session('status') }}</div>
    @endif

    {{-- Form --}}
    <form wire:submit="login" class="flex flex-col gap-5">

        <div class="form-control">
            <label class="label pb-1">
                <span class="label-text font-medium text-sm">Email address</span>
            </label>
            <input
                wire:model="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="you@example.com"
                class="input input-bordered w-full focus:input-primary @error('email') input-error @enderror"
            />
            @error('email')
                <span class="text-error text-xs mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-control">
            <label class="label pb-1">
                <span class="label-text font-medium text-sm">Password</span>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="label-text-alt link link-primary text-xs">
                        Forgot password?
                    </a>
                @endif
            </label>
            <input
                wire:model="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="input input-bordered w-full focus:input-primary @error('password') input-error @enderror"
            />
            @error('password')
                <span class="text-error text-xs mt-1">{{ $message }}</span>
            @enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" wire:model="remember" class="checkbox checkbox-primary checkbox-sm" />
            <span class="text-sm">Remember me</span>
        </label>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading class="loading loading-spinner loading-sm"></span>
        </button>

    </form>

    {{-- Divider --}}
    <div class="divider text-xs text-base-content/30">OR</div>

    {{-- Provider CTA --}}
    <div class="rounded-2xl border border-base-300 bg-base-200/50 p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
            <x-icon name="o-signal" class="w-5 h-5 text-primary" />
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold">Stream your content</p>
            <p class="text-xs text-base-content/50">Churches, schools, broadcasters & NGOs</p>
        </div>
        <a href="{{ route('provider.register') }}" wire:navigate
            class="btn btn-primary btn-sm btn-outline shrink-0">
            Apply
        </a>
    </div>

    <p class="text-center text-sm text-base-content/50">
        New subscriber?
        <a href="{{ route('register') }}" wire:navigate class="link link-primary font-medium">Create account</a>
    </p>

</div>
