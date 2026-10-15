<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Account creation only. Organisation, channel and KYC details are collected
 * inside the provider portal (Onboarding) once the provider is logged in.
 */
#[Title('Apply as a Provider')]
class Register extends Component
{
    use Toast;

    #[Rule(['required', 'string', 'max:255'])]
    public string $name = '';

    #[Rule(['required', 'string', 'max:200'])]
    public string $company_name = '';

    #[Rule(['required', 'email', 'max:255', 'unique:users,email'])]
    public string $email = '';

    #[Rule(['required', 'confirmed'])]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (auth()->check() && auth()->user()->role === 'provider') {
            $this->redirect(route('provider.dashboard'), navigate: true);
        }
    }

    public function submit(): void
    {
        $this->validate();
        $this->validate(['password' => [Password::defaults()]]);

        $user = DB::transaction(function (): User {
            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'password' => Hash::make($this->password),
                'role'     => 'provider',
            ]);

            $user->providerProfile()->create([
                'company_name'     => $this->company_name,
                'contact_person'   => $this->name,
                'setup_fee_status' => 'pending',
                'setup_fee_amount' => 0,
                'is_approved'      => false,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('provider.onboarding'), navigate: true);
    }

    public function render()
    {
        return view('livewire.provider.register')
            ->layout('components.layouts.provider-register');
    }
}
