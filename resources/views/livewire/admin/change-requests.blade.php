<div>
    <x-header title="Change Requests" subtitle="Provider channel update requests" separator progress-indicator>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
                ['id' => 'pending',  'name' => 'Pending'],
                ['id' => 'approved', 'name' => 'Approved'],
                ['id' => 'rejected', 'name' => 'Rejected'],
                ['id' => '',         'name' => 'All'],
            ]" option-value="id" option-label="name" class="select-sm" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$requests" with-pagination>
            @scope('cell_provider', $req)
                <div>
                    <p class="font-semibold text-sm">{{ $req->provider->company_name }}</p>
                    <p class="text-xs text-base-content/40">{{ $req->provider->user->email }}</p>
                </div>
            @endscope

            @scope('cell_channel', $req)
                {{ $req->channel->name }}
            @endscope

            @scope('cell_field', $req)
                <x-badge :value="$req->fieldLabel()" class="badge-ghost" />
            @endscope

            @scope('cell_current', $req)
                <span class="text-sm text-base-content/60 line-through">{{ $req->displayValue($req->current_value) }}</span>
            @endscope

            @scope('cell_requested', $req)
                <span class="text-sm font-medium">{{ $req->displayValue($req->requested_value) }}</span>
            @endscope

            @scope('cell_status', $req)
                <x-badge :value="ucfirst($req->status)" :class="
                    $req->status === 'pending'  ? 'badge-warning' :
                   ($req->status === 'approved' ? 'badge-success' : 'badge-error')" />
            @endscope

            @scope('cell_actions', $req)
                @if($req->status === 'pending')
                    <div class="flex gap-1 justify-end">
                        <x-button label="Approve" class="btn-success btn-xs"
                            wire:click="openReview('{{ $req->id }}', 'approve')" />
                        <x-button label="Reject" class="btn-error btn-outline btn-xs"
                            wire:click="openReview('{{ $req->id }}', 'reject')" />
                    </div>
                @else
                    <div class="text-xs text-base-content/40">
                        {{ $req->reviewed_at?->format('d M Y') }}
                        @if($req->admin_note)
                            <p class="italic">{{ $req->admin_note }}</p>
                        @endif
                    </div>
                @endif
            @endscope
        </x-table>
    </x-card>

    {{-- Review modal --}}
    <x-modal wire:model="showReviewModal"
        :title="$reviewAction === 'approve' ? 'Approve Change Request' : 'Reject Change Request'">
        <x-textarea label="Note to provider (optional)" wire:model="adminNote"
            placeholder="Reason for rejection, or any additional context..." rows="3" />

        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('showReviewModal', false)" />
            @if($reviewAction === 'approve')
                <x-button label="Approve & Apply" wire:click="submitReview" class="btn-success" spinner="submitReview" />
            @else
                <x-button label="Reject" wire:click="submitReview" class="btn-error" spinner="submitReview" />
            @endif
        </x-slot:actions>
    </x-modal>
</div>
