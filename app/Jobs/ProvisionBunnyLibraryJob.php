<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProviderProfile;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionBunnyLibraryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $providerId) {}

    public function handle(BunnyStreamService $bunny): void
    {
        $provider = ProviderProfile::find($this->providerId);

        if ($provider?->is_approved) {
            $bunny->provisionLibrary($provider);
        }
    }
}
