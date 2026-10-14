<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bunny calls the WebhookUrl set on each library at provisioning time, which
 * embeds BUNNY_WEBHOOK_SECRET as a query param.
 */
class VerifyBunnyWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.bunny.webhook_secret');
        $secret = (string) $request->query('secret', '');

        if ($expected === '' || $secret === '' || ! hash_equals($expected, $secret)) {
            abort(401);
        }

        return $next($request);
    }
}
