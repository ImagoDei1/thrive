<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $dashboard = match($user->role) {
            'admin'    => route('admin.dashboard', absolute: false),
            'provider' => route('provider.dashboard', absolute: false),
            default    => route('home', absolute: false),
        };

        return redirect()->intended($dashboard.'?verified=1');
    }
}
