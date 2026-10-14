<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\ContentItem;
use App\Services\VideoSourceService;

/**
 * Bunny player preview modal. Pair with <x-video-preview-modal />.
 * The using component authorizes and loads the item, then calls openPreview().
 */
trait HasVideoPreview
{
    public bool $showPreview = false;
    public ?string $previewTitle = null;
    public ?string $previewSubtitle = null;
    public ?string $previewUrl = null;

    public function updatedShowPreview(bool $value): void
    {
        // Unmount the iframe so playback stops when the modal closes.
        if (! $value) {
            $this->reset('previewTitle', 'previewSubtitle', 'previewUrl');
        }
    }

    protected function openPreview(ContentItem $item, ?string $subtitle = null): void
    {
        $url = app(VideoSourceService::class)->embedUrl($item);

        if (! $url) {
            $this->warning('This video is not ready for playback yet.');

            return;
        }

        $this->previewTitle = $item->title;
        $this->previewSubtitle = $subtitle;
        $this->previewUrl = $url;
        $this->showPreview = true;
    }
}
