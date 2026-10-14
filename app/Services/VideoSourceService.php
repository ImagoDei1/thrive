<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentItem;

/**
 * Single place that turns a content item into a playable URL.
 * Controllers and views must not build Bunny URLs themselves.
 *
 * content_items.hls_url / thumbnail_url hold the canonical (unsigned) CDN
 * URLs; signing happens here at read time so stored URLs never expire.
 */
class VideoSourceService
{
    public function hlsUrl(ContentItem $item): ?string
    {
        if ($item->status !== 'ready' || ! $item->hls_url) {
            return null;
        }

        return $this->sign($item, $item->hls_url);
    }

    public function thumbnailUrl(ContentItem $item): ?string
    {
        return $item->thumbnail_url ? $this->sign($item, $item->thumbnail_url) : null;
    }

    /**
     * Bunny's hosted iframe player. Used for admin previews; the Flutter app
     * plays hlsUrl() directly so heartbeats stay under its control.
     */
    public function embedUrl(ContentItem $item, bool $autoplay = true): ?string
    {
        if ($item->status !== 'ready' || ! $item->bunny_video_id || ! $item->bunny_library_id) {
            return null;
        }

        $query = [
            'autoplay'   => $autoplay ? 'true' : 'false',
            'preload'    => 'true',
            'responsive' => 'true',
        ];

        $key = $item->channel?->provider?->bunny_token_key;

        if (config('services.bunny.token_auth') && $key) {
            // Embed view token: SHA256_HEX(token_key + video_id + expires).
            $expires = time() + (int) config('services.bunny.url_ttl_seconds');
            $query['token'] = hash('sha256', $key . $item->bunny_video_id . $expires);
            $query['expires'] = $expires;
        }

        return rtrim((string) config('services.bunny.embed_base'), '/')
            . "/{$item->bunny_library_id}/{$item->bunny_video_id}?" . http_build_query($query);
    }

    private function sign(ContentItem $item, string $url): string
    {
        if (! $item->bunny_video_id || ! config('services.bunny.token_auth')) {
            return $url;
        }

        $key = $item->channel?->provider?->bunny_token_key;

        if (! $key) {
            return $url;
        }

        // Directory token so the player can fetch every rendition/segment under /{guid}/.
        return self::signBunnyUrl($url, $key, (int) config('services.bunny.url_ttl_seconds'), "/{$item->bunny_video_id}/");
    }

    /**
     * Bunny CDN token authentication (SHA256, path-based directory token).
     */
    public static function signBunnyUrl(string $url, string $securityKey, int $ttlSeconds, string $tokenPath, ?int $now = null): string
    {
        $parts = parse_url($url);
        $expires = ($now ?? time()) + $ttlSeconds;

        $parameters = [];
        parse_str($parts['query'] ?? '', $parameters);
        $parameters['token_path'] = $tokenPath;
        ksort($parameters);

        $parameterData = collect($parameters)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');
        $parameterDataUrl = collect($parameters)->map(fn ($v, $k) => "&{$k}=" . urlencode((string) $v))->implode('');

        $token = base64_encode(hash('sha256', $securityKey . $tokenPath . $expires . $parameterData, true));
        $token = str_replace('=', '', strtr($token, '+/', '-_'));

        return "{$parts['scheme']}://{$parts['host']}/bcdn_token={$token}&expires={$expires}{$parameterDataUrl}{$parts['path']}";
    }
}
