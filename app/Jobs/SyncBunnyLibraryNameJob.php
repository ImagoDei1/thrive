<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProviderProfile;
use App\Services\Bunny\BunnyStreamClient;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBunnyLibraryNameJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $providerId) {}

    public function handle(BunnyStreamService $bunny, BunnyStreamClient $client): void
    {
        // Renaming is cosmetic; skip quietly when Bunny is not configured (e.g. tests).
        if (! $client->isConfigured()) {
            return;
        }

        $provider = ProviderProfile::find($this->providerId);

        if ($provider?->hasBunnyLibrary()) {
            $bunny->syncLibraryName($provider);
        }
    }
}
