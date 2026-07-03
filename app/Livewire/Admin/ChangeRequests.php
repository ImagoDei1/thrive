<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ChannelChangeRequest;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Change Requests')]
class ChangeRequests extends Component
{
    use Toast, WithPagination;

    public bool $showReviewModal = false;
    public string $reviewingId   = '';
    public string $adminNote     = '';
    public string $reviewAction  = '';

    public string $statusFilter = 'pending';

    public array $headers = [
        ['key' => 'provider',  'label' => 'Provider'],
        ['key' => 'channel',   'label' => 'Channel'],
        ['key' => 'field',     'label' => 'Field'],
        ['key' => 'current',   'label' => 'Current'],
        ['key' => 'requested', 'label' => 'Requested'],
        ['key' => 'status',    'label' => 'Status'],
        ['key' => 'actions',   'label' => ''],
    ];

    public function openReview(string $id, string $action): void
    {
        $this->reviewingId  = $id;
        $this->reviewAction = $action;
        $this->adminNote    = '';
        $this->showReviewModal = true;
    }

    public function submitReview(): void
    {
        $request = ChannelChangeRequest::with('channel')->findOrFail($this->reviewingId);

        if ($this->reviewAction === 'approve') {
            // Apply the change to the channel
            $request->channel->update([
                $request->field => $request->requested_value,
            ]);

            $request->update([
                'status'      => 'approved',
                'admin_note'  => $this->adminNote,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->success("Change approved — {$request->channel->name} {$request->fieldLabel()} updated.");
        } else {
            $request->update([
                'status'      => 'rejected',
                'admin_note'  => $this->adminNote,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->warning('Change request rejected.');
        }

        $this->showReviewModal = false;
        $this->reset('reviewingId', 'adminNote', 'reviewAction');
    }

    public function render()
    {
        $requests = ChannelChangeRequest::with(['channel', 'provider.user'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.change-requests', [
            'requests' => $requests,
        ])->layout('components.admin-layout');
    }
}
