<?php

declare(strict_types=1);

namespace App\Services\Bunny;

use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\ProviderProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BunnyStreamService
{
    public function __construct(private readonly BunnyStreamClient $client) {}

    /**
     * Create the provider's Bunny library (idempotent) and store its credentials.
     */
    public function provisionLibrary(ProviderProfile $provider): ProviderProfile
    {
        if ($provider->hasBunnyLibrary()) {
            return $provider;
        }

        return Cache::lock("bunny:provision-library:{$provider->id}", 120)
            ->block(60, function () use ($provider): ProviderProfile {
                $provider->refresh();

                return $provider->hasBunnyLibrary() ? $provider : $this->createLibraryFor($provider);
            });
    }

    private function createLibraryFor(ProviderProfile $provider): ProviderProfile
    {
        $webhookUrl = route('webhooks.bunny', ['secret' => config('services.bunny.webhook_secret')]);
        $library = $this->client->createLibrary($this->libraryName($provider), $webhookUrl, (bool) config('services.bunny.token_auth'));
        $pullZone = $this->client->getPullZone($library['PullZoneId']);

        $hostname = collect($pullZone['Hostnames'] ?? [])
            ->sortByDesc(fn (array $h) => (bool) ($h['IsSystemHostname'] ?? false))
            ->pluck('Value')
            ->first();

        $provider->update([
            'bunny_library_id'      => (string) $library['Id'],
            'bunny_library_api_key' => $library['ApiKey'],
            'bunny_token_key'       => $pullZone['ZoneSecurityKey'] ?? null,
            'bunny_cdn_hostname'    => $hostname,
        ]);

        return $provider;
    }

    /**
     * Library name shown in the Bunny dashboard: the slug of the provider's
     * first channel, or the company name slug before any channel exists.
     */
    public function libraryName(ProviderProfile $provider): string
    {
        $name = $provider->channels()->oldest()->value('slug')
            ?: Str::slug((string) $provider->company_name)
            ?: "provider-{$provider->id}";

        return Str::limit($name, 100, '');
    }

    /** Rename the provider's Bunny library to match libraryName(). */
    public function syncLibraryName(ProviderProfile $provider): ?string
    {
        if (! $provider->hasBunnyLibrary()) {
            return null;
        }

        $name = $this->libraryName($provider);
        $this->client->updateLibrary($provider->bunny_library_id, ['Name' => $name]);

        return $name;
    }

    /**
     * Register a video with Bunny and return the content item plus browser TUS
     * credentials. Re-uses a matching in-progress item so an interrupted
     * upload of the same file can resume.
     *
     * @return array{item: ContentItem, tus: array<string, mixed>}
     */
    public function initiateUpload(
        Channel $channel,
        string $title,
        ?string $description,
        string $fileName,
        int $fileSize,
        string $fileType,
    ): array {
        $provider = $this->provisionLibrary($channel->provider);

        $item = ContentItem::where('channel_id', $channel->id)
            ->where('status', 'uploading')
            ->whereNotNull('bunny_video_id')
            ->where('file_path', $fileName)
            ->where('file_size_bytes', $fileSize)
            ->where('created_at', '>', now()->subSeconds((int) config('services.bunny.upload_ttl_seconds')))
            ->latest()
            ->first();

        if ($item) {
            $item->update(['title' => $title, 'description' => $description]);
        } else {
            $video = $this->client->createVideo($provider->bunny_library_id, $provider->bunny_library_api_key, $title);

            $item = DB::transaction(fn () => ContentItem::create([
                'channel_id'       => $channel->id,
                'title'            => $title,
                'description'      => $description,
                'type'             => 'vod',
                'file_path'        => $fileName, // original client filename, used to match resumes
                'file_size_bytes'  => $fileSize,
                'status'           => 'uploading',
                'bunny_library_id' => $provider->bunny_library_id,
                'bunny_video_id'   => $video['guid'],
            ]));
        }

        return [
            'item' => $item,
            'tus'  => $this->tusCredentials($provider, $item, $fileType),
        ];
    }

    /** @return array<string, mixed> */
    public function tusCredentials(ProviderProfile $provider, ContentItem $item, string $fileType): array
    {
        $expires = now()->addSeconds((int) config('services.bunny.upload_ttl_seconds'))->getTimestamp();

        return [
            'endpoint'       => config('services.bunny.tus_endpoint'),
            'content_item_id' => $item->id,
            'headers'        => [
                'AuthorizationSignature' => hash('sha256', $provider->bunny_library_id . $provider->bunny_library_api_key . $expires . $item->bunny_video_id),
                'AuthorizationExpire'    => (string) $expires,
                'VideoId'                => $item->bunny_video_id,
                'LibraryId'              => $provider->bunny_library_id,
            ],
            'metadata' => [
                'filetype' => $fileType,
                'title'    => $item->title,
            ],
        ];
    }

    /**
     * Remove the video (and its renditions) from Bunny storage.
     */
    public function deleteVideo(ContentItem $item): void
    {
        $provider = $this->providerFor($item);

        if (! $item->bunny_video_id || ! $provider?->hasBunnyLibrary() || $provider->bunny_library_id !== $item->bunny_library_id) {
            return;
        }

        $this->client->deleteVideo($item->bunny_library_id, $provider->bunny_library_api_key, $item->bunny_video_id);
    }

    /**
     * Pull authoritative state for a video from Bunny and apply it.
     */
    public function syncVideo(ContentItem $item): ContentItem
    {
        $provider = $this->providerFor($item);

        if (! $provider?->hasBunnyLibrary() || $provider->bunny_library_id !== $item->bunny_library_id) {
            return $item;
        }

        $video = $this->client->getVideo($item->bunny_library_id, $provider->bunny_library_api_key, $item->bunny_video_id);
        $status = BunnyVideoStatus::tryFrom((int) ($video['status'] ?? -1));

        if ($status === null) {
            return $item;
        }

        $newStatus = $status->contentStatus();

        // Never move backwards from a terminal state because of a late/duplicate event.
        if ($item->status === 'ready' && $newStatus !== 'ready') {
            return $item;
        }

        $attributes = ['status' => $newStatus];

        if ($newStatus === 'ready') {
            $host = $provider->bunny_cdn_hostname;
            $guid = $item->bunny_video_id;
            $resolutions = array_values(array_filter(explode(',', (string) ($video['availableResolutions'] ?? ''))));

            $attributes += [
                'hls_url'          => $host ? "https://{$host}/{$guid}/playlist.m3u8" : null,
                'thumbnail_url'    => $host && ! empty($video['thumbnailFileName']) ? "https://{$host}/{$guid}/{$video['thumbnailFileName']}" : null,
                'duration_seconds' => (int) round((float) ($video['length'] ?? 0)),
                'resolution'       => isset($video['width'], $video['height']) && $video['height'] ? "{$video['width']}x{$video['height']}" : null,
                'qualities'        => $resolutions ?: null,
                'file_size_bytes'  => (int) (Arr::get($video, 'storageSize') ?: $item->file_size_bytes),
                'published_at'     => $item->published_at ?? now(),
            ];
        }

        $item->update($attributes);

        return $item;
    }

    private function providerFor(ContentItem $item): ?ProviderProfile
    {
        return $item->channel()->withTrashed()->first()?->provider()->withTrashed()->first();
    }
}
