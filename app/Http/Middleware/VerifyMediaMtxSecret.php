<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMediaMtxSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $request->query('secret', '');

        if ($secret === '' || ! hash_equals((string) config('streaming.mediamtx_secret'), $secret)) {
            abort(401);
        }

        return $next($request);
    }
}
