<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\Channel;
use App\Models\ChannelChangeRequest;
use App\Models\ReferenceValue;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

#[Title('Channel Info')]
class ChannelInfo extends Component
{
    use Toast, WithFileUploads;

    public Channel $channel;

    public bool $showRequestForm = false;

    #[Rule('nullable|image|max:2048')]
    public $logo = null;

    public string $requestField = '';

    public string $requestedValue = '';

    public array $editableFields = [
        ['id' => 'name',        'name' => 'Channel Name'],
        ['id' => 'description', 'name' => 'Description'],
        ['id' => 'category',    'name' => 'Category'],
        ['id' => 'language',    'name' => 'Language'],
    ];

    protected function rules(): array
    {
        return [
            'requestField'   => ['required', 'in:' . collect($this->editableFields)->pluck('id')->implode(',')],
            'requestedValue' => match ($this->requestField) {
                'category' => ['required', ReferenceValue::rule(ReferenceValue::CHANNEL_CATEGORY)],
                'language' => ['required', ReferenceValue::rule(ReferenceValue::CHANNEL_LANGUAGE)],
                default    => ['required', 'min:2', 'max:200'],
            },
        ];
    }

    public function updatedRequestField(): void
    {
        $this->requestedValue = '';
        $this->resetValidation('requestedValue');
    }

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    public function openRequest(string $field = ''): void
    {
        $this->requestField    = $field;
        $this->requestedValue  = '';
        $this->showRequestForm = true;
    }

    public function submitRequest(): void
    {
        $this->validate();

        $hasPending = ChannelChangeRequest::where('channel_id', $this->channel->id)
            ->where('field', $this->requestField)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            $this->error('You already have a pending request for this field. Wait for it to be reviewed.');
            return;
        }

        ChannelChangeRequest::create([
            'channel_id'      => $this->channel->id,
            'provider_id'     => auth()->user()->providerProfile->id,
            'field'           => $this->requestField,
            'current_value'   => $this->channel->{$this->requestField},
            'requested_value' => $this->requestedValue,
        ]);

        $this->showRequestForm = false;
        $this->reset('requestField', 'requestedValue');
        $this->success('Change request submitted. An admin will review it shortly.');
    }

    public function uploadLogo(): void
    {
        $this->validateOnly('logo');

        $path = $this->logo->store('channels/logos', 'public');

        // Delete old logo if it was stored locally
        if ($this->channel->logo_url && str_starts_with($this->channel->logo_url, '/storage/')) {
            $old = str_replace('/storage/', '', $this->channel->logo_url);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }

        $this->channel->update(['logo_url' => '/storage/' . $path]);
        $this->reset('logo');
        $this->success('Logo updated.');
    }

    public function render()
    {
        $pendingRequests = ChannelChangeRequest::where('channel_id', $this->channel->id)
            ->where('status', 'pending')
            ->pluck('field')
            ->toArray();

        $recentRequests = ChannelChangeRequest::where('channel_id', $this->channel->id)
            ->latest()
            ->take(10)
            ->get();

        return view('livewire.provider.channel-info', [
            'pendingRequests' => $pendingRequests,
            'recentRequests'  => $recentRequests,
            'categories'      => ReferenceValue::selectOptions(ReferenceValue::CHANNEL_CATEGORY),
            'languages'       => ReferenceValue::selectOptions(ReferenceValue::CHANNEL_LANGUAGE),
        ])->layout('components.provider-layout');
    }
}
