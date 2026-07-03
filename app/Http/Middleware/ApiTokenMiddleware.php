<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->input('api_token');

        if (! $token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = User::where('api_token', $token)->where('is_active', true)->first();

        if (! $user) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);

        return $next($request);
    }
}
