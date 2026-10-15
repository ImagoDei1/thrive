<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProviderProfile;
use App\Services\Bunny\BunnyStreamClient;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProvisionBunnyLibraryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $providerId) {}

    public function handle(BunnyStreamService $bunny, BunnyStreamClient $client): void
    {
        // Without an account key there is nothing to call. Uploads provision lazily later.
        if (! $client->isConfigured()) {
            Log::warning('Skipping Bunny library provisioning: BUNNY_API_KEY is not set.', ['provider' => $this->providerId]);

            return;
        }

        $provider = ProviderProfile::find($this->providerId);

        if ($provider?->is_approved) {
            $bunny->provisionLibrary($provider);
        }
    }
}
