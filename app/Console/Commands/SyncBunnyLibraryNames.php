<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProviderProfile;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Console\Command;
use Throwable;

class SyncBunnyLibraryNames extends Command
{
    protected $signature   = 'bunny:sync-library-names';
    protected $description = 'Rename every provider Bunny library to its first channel slug (or company name slug)';

    public function handle(BunnyStreamService $bunny): int
    {
        $providers = ProviderProfile::whereNotNull('bunny_library_id')->get();
        $failed = 0;

        foreach ($providers as $provider) {
            try {
                $name = $bunny->syncLibraryName($provider);
                $this->line("{$provider->bunny_library_id} → {$name}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$provider->bunny_library_id}: {$e->getMessage()}");
            }
        }

        $this->info("Renamed " . ($providers->count() - $failed) . " of {$providers->count()} library(ies).");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
