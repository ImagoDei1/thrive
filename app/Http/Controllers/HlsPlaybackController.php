<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\RedirectResponse;

class HlsPlaybackController extends Controller
{
    /**
     * Public playback URLs use the channel slug so rtmp_key (the publish
     * secret typed into the encoder) never appears in a link shown to
     * subscribers. Redirects to nginx's public "/live/" location, which
     * reverse-proxies to MediaMTX's HLS output on 127.0.0.1:8888 unchanged —
     * the path must stay identical to the upstream one ("live/{rtmp_key}")
     * because MediaMTX issues absolute-path redirects (?cookieCheck=1) and
     * session cookies that break under any path-rewriting proxy.
     */
    public function __invoke(string $slug, string $file): RedirectResponse
    {
        $channel = Channel::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return redirect("/live/{$channel->rtmp_key}/{$file}");
    }
}
