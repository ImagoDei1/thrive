<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Apply as a Provider')]
class Register extends Component
{
    use Toast;

    public int $step = 1;

    // Step 1 — account
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Step 2 — profile
    public string $company_name = '';
    public string $contact_person = '';
    public string $contact_phone = '';
    public string $description = '';

    // Step 3 — channel
    public string $channel_name = '';
    public string $channel_category = '';
    public string $channel_language = 'en';

    public function mount(): void
    {
        if (auth()->check() && auth()->user()->role === 'provider') {
            $this->redirect(route('provider.onboarding'), navigate: true);
        }
    }

    public function nextStep(): void
    {
        match ($this->step) {
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            default => null,
        };

        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    private function validateStep1(): void
    {
        $this->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::defaults()],
        ]);
    }

    private function validateStep2(): void
    {
        $this->validate([
            'company_name'   => ['required', 'string', 'max:200'],
            'contact_person' => ['required', 'string', 'max:200'],
            'contact_phone'  => ['required', 'string', 'max:30'],
            'description'    => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public function submit(): void
    {
        $this->validate([
            'channel_name'     => ['required', 'string', 'min:3', 'max:100'],
            'channel_category' => ['nullable', 'string', 'max:50'],
            'channel_language' => ['required', 'size:2'],
        ]);

        $user = User::create([
            'name'     => $this->name,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
            'role'     => 'provider',
        ]);

        event(new Registered($user));

        $provider = $user->providerProfile()->create([
            'company_name'    => $this->company_name,
            'contact_person'  => $this->contact_person,
            'contact_phone'   => $this->contact_phone,
            'description'     => $this->description,
            'setup_fee_status' => 'pending',
            'setup_fee_amount' => 0,
            'is_approved'     => false,
        ]);

        $slug = Str::slug($this->channel_name) . '-' . Str::random(4);

        $channel = $provider->channels()->create([
            'name'            => $this->channel_name,
            'slug'            => $slug,
            'category'        => $this->channel_category,
            'language'        => $this->channel_language,
            'rtmp_key'        => Str::random(32),
            'rtmp_ingest_url' => config('streaming.rtmp_ingest_url'),
            'is_active'       => false,
        ]);

        $channel->update([
            'hls_playback_url' => route('hls.playback', ['slug' => $slug, 'file' => 'index.m3u8']),
        ]);

        Auth::login($user);

        $this->redirect(route('provider.onboarding'), navigate: true);
    }

    public function render()
    {
        return view('livewire.provider.register')
            ->layout('components.layouts.provider-register');
    }
}
