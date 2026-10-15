<?php

declare(strict_types=1);

namespace App\Services\Bunny;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP wrapper over the Bunny account API and Stream API.
 * Domain code should go through BunnyStreamService / VideoSourceService.
 */
class BunnyStreamClient
{
    // ---------------------------------------------------------------------------
    // Account API (BUNNY_API_KEY)
    // ---------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function createLibrary(string $name, ?string $webhookUrl, bool $tokenAuth): array
    {
        $library = $this->account()
            ->post('/videolibrary', ['Name' => $name])
            ->throw()
            ->json();

        $this->updateLibrary((string) $library['Id'], array_filter([
            'WebhookUrl'                => $webhookUrl,
            'EnableTokenAuthentication' => $tokenAuth,
        ], fn ($v) => $v !== null));

        return $library;
    }

    /** @param  array<string, mixed>  $settings  e.g. ['Name' => '...'] */
    public function updateLibrary(string $libraryId, array $settings): void
    {
        $this->account()->post("/videolibrary/{$libraryId}", $settings)->throw();
    }

    /** @return array<string, mixed> */
    public function getPullZone(int|string $pullZoneId): array
    {
        return $this->account()->get("/pullzone/{$pullZoneId}")->throw()->json();
    }

    // ---------------------------------------------------------------------------
    // Stream API (library-scoped key)
    // ---------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function createVideo(string $libraryId, string $libraryApiKey, string $title): array
    {
        return $this->stream($libraryApiKey)
            ->post("/{$libraryId}/videos", ['title' => $title])
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function getVideo(string $libraryId, string $libraryApiKey, string $videoId): array
    {
        return $this->stream($libraryApiKey)
            ->get("/{$libraryId}/videos/{$videoId}")
            ->throw()
            ->json();
    }

    /** Deleting a video that no longer exists counts as success. */
    public function deleteVideo(string $libraryId, string $libraryApiKey, string $videoId): void
    {
        $response = $this->stream($libraryApiKey)->delete("/{$libraryId}/videos/{$videoId}");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    // ---------------------------------------------------------------------------

    /** False when BUNNY_API_KEY is unset, e.g. in tests or a fresh install. */
    public function isConfigured(): bool
    {
        return (string) config('services.bunny.api_key') !== '';
    }

    private function account(): PendingRequest
    {
        $key = (string) config('services.bunny.api_key');

        if ($key === '') {
            throw new RuntimeException('BUNNY_API_KEY is not configured.');
        }

        return Http::baseUrl((string) config('services.bunny.account_api_base'))
            ->withHeaders(['AccessKey' => $key])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function stream(string $libraryApiKey): PendingRequest
    {
        return Http::baseUrl((string) config('services.bunny.stream_api_base'))
            ->withHeaders(['AccessKey' => $libraryApiKey])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }
}
