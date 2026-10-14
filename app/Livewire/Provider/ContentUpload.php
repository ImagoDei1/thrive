<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\Playlist;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

/**
 * Full-page, multi-file upload queue. Livewire owns the queue (titles,
 * descriptions, statuses); resources/js/bunny-upload.js holds the File
 * objects and streams each one straight to Bunny over TUS.
 *
 * Row status: pending → uploading → done | failed
 */
#[Title('Upload Videos')]
class ContentUpload extends Component
{
    use Toast;

    public const MAX_FILES = 20;

    public Channel $channel;

    /** @var array<string, array{name: string, size: int, type: string, title: string, description: ?string, status: string, content_item_id: ?string, error: ?string}> */
    #[Rule([
        'uploads.*.title'       => 'required|string|max:255',
        'uploads.*.description' => 'nullable|string|max:5000',
    ], attribute: [
        'uploads.*.title'       => 'title',
        'uploads.*.description' => 'description',
    ])]
    public array $uploads = [];

    /** Playlists every upload started from this page is appended to. */
    #[Rule(['playlistIds' => 'array|max:10', 'playlistIds.*' => 'uuid'])]
    public array $playlistIds = [];

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    /**
     * Called from JS with metadata for newly selected files.
     *
     * @param  list<array{key: string, name: string, size: int, type: string}>  $files
     */
    public function addFiles(array $files): void
    {
        $max = (int) config('services.bunny.max_upload_bytes');
        $rejected = [];

        foreach ($files as $file) {
            $key = (string) ($file['key'] ?? '');
            $name = Str::limit((string) ($file['name'] ?? ''), 250, '');
            $size = (int) ($file['size'] ?? 0);
            $type = (string) ($file['type'] ?? '');

            if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) || isset($this->uploads[$key]) || $name === '') {
                continue;
            }

            $reason = match (true) {
                count($this->uploads) >= self::MAX_FILES => 'queue is full (max ' . self::MAX_FILES . ')',
                ! str_starts_with($type, 'video/')        => 'not a video file',
                $size < 1                                 => 'file is empty',
                $size > $max                              => 'larger than ' . round($max / 1024 ** 3, 1) . ' GB',
                $this->isQueued($name, $size)             => 'already in the queue',
                default                                   => null,
            };

            if ($reason !== null) {
                $rejected[] = "{$name}: {$reason}";

                continue;
            }

            $this->uploads[$key] = [
                'name'            => $name,
                'size'            => $size,
                'type'            => $type,
                'title'           => Str::of(pathinfo($name, PATHINFO_FILENAME))->replace(['_', '-', '.'], ' ')->squish()->limit(255, '')->toString(),
                'description'     => null,
                'status'          => 'pending',
                'content_item_id' => null,
                'error'           => null,
            ];
        }

        if ($rejected) {
            $this->warning('Some files were skipped', implode(' · ', $rejected), timeout: 8000);
        }
    }

    public function remove(string $key): void
    {
        if (in_array($this->uploads[$key]['status'] ?? null, ['pending', 'failed', 'done'], true)) {
            unset($this->uploads[$key]);
            $this->resetValidation(["uploads.{$key}.title", "uploads.{$key}.description"]);
        }
    }

    public function clearFinished(): void
    {
        $this->uploads = array_filter($this->uploads, fn (array $u) => $u['status'] !== 'done');
    }

    /** Start every pending row (and retry failed ones). */
    public function startAll(BunnyStreamService $bunny): void
    {
        $keys = array_keys(array_filter($this->uploads, fn (array $u) => in_array($u['status'], ['pending', 'failed'], true)));

        if (! $keys) {
            return;
        }

        $this->validate();

        $started = [];

        foreach ($keys as $key) {
            if ($tus = $this->initiate($bunny, $key)) {
                $started[] = ['key' => $key, 'tus' => $tus];
            }
        }

        if ($started) {
            $this->dispatch('bunny-upload-start', uploads: $started);
        }
    }

    public function retry(BunnyStreamService $bunny, string $key): void
    {
        if (($this->uploads[$key]['status'] ?? null) !== 'failed') {
            return;
        }

        $this->validateOnly("uploads.{$key}.title");

        if ($tus = $this->initiate($bunny, $key)) {
            $this->dispatch('bunny-upload-start', uploads: [['key' => $key, 'tus' => $tus]]);
        }
    }

    #[On('bunny-upload-finished')]
    public function uploadFinished(string $key, string $id): void
    {
        $item = $this->ownedItem($id);

        if ($item->status === 'uploading') {
            $item->update(['status' => 'processing']);
        }

        if (isset($this->uploads[$key])) {
            $this->uploads[$key]['status'] = 'done';
            $this->uploads[$key]['error'] = null;
        }

        if (! $this->hasActive()) {
            $this->success('All uploads finished. Videos will be published once processing completes.');
        }
    }

    #[On('bunny-upload-failed')]
    public function uploadFailed(string $key, string $id, string $message = ''): void
    {
        // Ownership check only. The item stays 'uploading' so a retry resumes it.
        $this->ownedItem($id);
        Log::warning('Bunny TUS upload failed', ['content_item' => $id, 'message' => $message]);

        if (isset($this->uploads[$key])) {
            $this->uploads[$key]['status'] = 'failed';
            $this->uploads[$key]['error'] = 'Upload interrupted. Retry to resume where it stopped.';
        }
    }

    public function render()
    {
        return view('livewire.provider.content-upload', [
            'counts'    => array_count_values(array_column($this->uploads, 'status')),
            'playlists' => Playlist::where('channel_id', $this->channel->id)->orderBy('title')->get(['id', 'title', 'kind']),
        ])->layout('components.provider-layout');
    }

    /** Formats bytes without the intl extension (not installed on this server). */
    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i ? 1 : 0) . ' ' . $units[$i];
    }

    // ---------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function initiate(BunnyStreamService $bunny, string $key): ?array
    {
        $row = $this->uploads[$key];

        try {
            $result = $bunny->initiateUpload(
                $this->channel,
                $row['title'],
                $row['description'] ?: null,
                $row['name'],
                $row['size'],
                $row['type'],
            );
        } catch (Throwable $e) {
            Log::error('Bunny upload initiation failed', ['channel' => $this->channel->id, 'file' => $row['name'], 'error' => $e->getMessage()]);
            $this->uploads[$key]['status'] = 'failed';
            $this->uploads[$key]['error'] = 'Could not start the upload. Try again shortly.';

            return null;
        }

        $this->uploads[$key]['status'] = 'uploading';
        $this->uploads[$key]['error'] = null;
        $this->uploads[$key]['content_item_id'] = $result['item']->id;

        $this->addToPlaylists($result['item']);

        return $result['tus'];
    }

    /** Series uploads land in the latest season. Re-adding on retry is a no-op. */
    private function addToPlaylists(ContentItem $item): void
    {
        if (! $this->playlistIds) {
            return;
        }

        Playlist::where('channel_id', $this->channel->id)
            ->whereIn('id', $this->playlistIds)
            ->get()
            ->each(fn (Playlist $playlist) => $playlist->addVideos([$item]));
    }

    private function isQueued(string $name, int $size): bool
    {
        foreach ($this->uploads as $u) {
            if ($u['name'] === $name && $u['size'] === $size && $u['status'] !== 'done') {
                return true;
            }
        }

        return false;
    }

    private function hasActive(): bool
    {
        foreach ($this->uploads as $u) {
            if ($u['status'] === 'uploading') {
                return true;
            }
        }

        return false;
    }

    private function ownedItem(string $id): ContentItem
    {
        $item = ContentItem::findOrFail($id);
        abort_unless($item->channel_id === $this->channel->id, 403);

        return $item;
    }
}
