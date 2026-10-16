<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ReferenceValue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Reference Data')]
class ReferenceData extends Component
{
    use Toast;

    #[Url(as: 'type')]
    public string $selectedType = ReferenceValue::CHANNEL_CATEGORY;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $code = '';
    public string $label = '';
    public string $description = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public array $headers = [
        ['key' => 'sort_order', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'label',      'label' => 'Value'],
        ['key' => 'usage',      'label' => 'In use'],
        ['key' => 'is_active',  'label' => 'Status'],
        ['key' => 'actions',    'label' => ''],
    ];

    public function mount(): void
    {
        if (! array_key_exists($this->selectedType, ReferenceValue::TYPES)) {
            $this->selectedType = ReferenceValue::CHANNEL_CATEGORY;
        }
    }

    public function rules(): array
    {
        return [
            // Code is stored on referencing records, so it is fixed after creation.
            'code'        => $this->editingId ? [] : [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                ValidationRule::unique('reference_values', 'code')->where('type', $this->selectedType),
            ],
            'label'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active'   => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return ['code.regex' => 'Use lowercase letters, numbers and underscores only.'];
    }

    public function updatedSelectedType(): void
    {
        if (! array_key_exists($this->selectedType, ReferenceValue::TYPES)) {
            $this->selectedType = ReferenceValue::CHANNEL_CATEGORY;
        }
    }

    public function updatedLabel(string $value): void
    {
        if (! $this->editingId) {
            $this->code = Str::of($value)->slug('_')->limit(50, '')->toString();
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = (int) ReferenceValue::where('type', $this->selectedType)->max('sort_order') + 10;
        $this->showModal  = true;
    }

    public function edit(string $id): void
    {
        $value = $this->find($id);

        $this->editingId   = $value->id;
        $this->code        = $value->code;
        $this->label       = $value->label;
        $this->description = (string) $value->description;
        $this->sort_order  = $value->sort_order;
        $this->is_active   = $value->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['description'] = $data['description'] ?: null;

        if ($this->editingId) {
            $value = $this->find($this->editingId);
            unset($data['code']);

            if ($value->is_system) {
                $data['is_active'] = true;
            }

            $value->update($data);
        } else {
            ReferenceValue::create($data + ['type' => $this->selectedType]);
        }

        $this->showModal = false;
        $this->success($this->editingId ? 'Value updated.' : 'Value added.');
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $value = $this->find($id);

        if ($value->is_system) {
            $this->error("{$value->label} is required by the platform and cannot be hidden.");
            return;
        }

        $value->update(['is_active' => ! $value->is_active]);

        $value->is_active
            ? $this->success("{$value->label} is available for selection again.")
            : $this->warning("{$value->label} hidden from new selections. Existing records keep it.");
    }

    public function delete(string $id): void
    {
        $value = $this->find($id);

        if ($value->is_system) {
            $this->error("{$value->label} is required by the platform and cannot be deleted.");
            return;
        }

        if (($count = $value->usageCount()) > 0) {
            $this->error("{$value->label} is used by {$count} " . Str::plural('record', $count) . '. Hide it instead.');
            return;
        }

        $value->delete();
        $this->success("{$value->label} deleted.");
    }

    private function find(string $id): ReferenceValue
    {
        return ReferenceValue::where('type', $this->selectedType)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'label', 'description', 'sort_order', 'is_active');
        $this->resetValidation();
    }

    public function render()
    {
        $values = ReferenceValue::query()->ofType($this->selectedType)->get();
        $usage  = ReferenceValue::usageCounts($this->selectedType);

        return view('livewire.admin.reference-data', [
            'types'  => ReferenceValue::TYPES,
            'values' => $values,
            'usage'  => $usage,
        ])->layout('components.admin-layout');
    }
}
