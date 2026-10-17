<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayChangu signs each webhook with an HMAC-SHA256 of the raw body, keyed by
 * the webhook secret from the PayChangu dashboard, in the "Signature" header.
 */
class VerifyPayChanguSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret    = (string) config('services.paychangu.webhook_secret');
        $signature = (string) $request->header('Signature', '');

        if ($secret === '' || $signature === '') {
            abort(401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, strtolower($signature))) {
            abort(401);
        }

        return $next($request);
    }
}
