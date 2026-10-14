<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProvisionBunnyLibraryJob;
use App\Models\ProviderProfile;
use Illuminate\Console\Command;

class ProvisionBunnyLibraries extends Command
{
    protected $signature   = 'bunny:provision-libraries';
    protected $description = 'Queue Bunny library creation for every approved provider that does not have one yet';

    public function handle(): int
    {
        $ids = ProviderProfile::query()
            ->where('is_approved', true)
            ->where(fn ($q) => $q->whereNull('bunny_library_id')->orWhereNull('bunny_library_api_key'))
            ->pluck('id');

        $ids->each(fn (string $id) => ProvisionBunnyLibraryJob::dispatch($id));

        $this->info("Queued library provisioning for {$ids->count()} provider(s).");

        return self::SUCCESS;
    }
}
