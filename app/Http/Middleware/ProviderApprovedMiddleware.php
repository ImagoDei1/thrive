<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProviderApprovedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'provider') {
            $profile = $user->providerProfile;

            if (! $profile || ! $profile->is_approved) {
                return redirect()->route('provider.onboarding');
            }
        }

        return $next($request);
    }
}
