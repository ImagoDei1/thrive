<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Subscription Plans')]
class SubscriptionPlans extends Component
{
    use Toast;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $price_mwk = '';
    public int $duration_days = 30;
    public int $grace_days = 0;
    public bool $is_active = true;
    public string $features = '';

    public array $headers = [
        ['key' => 'name',                'label' => 'Plan'],
        ['key' => 'duration_days',       'label' => 'Duration'],
        ['key' => 'price_mwk',           'label' => 'Price (MWK)'],
        ['key' => 'grace_days',          'label' => 'Grace'],
        ['key' => 'subscriptions_count', 'label' => 'Active subs'],
        ['key' => 'is_active',           'label' => 'Status'],
        ['key' => 'actions',             'label' => ''],
    ];

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'slug'          => [
                'required', 'alpha_dash', 'max:50',
                ValidationRule::unique('subscription_plans', 'slug')->ignore($this->editingId),
            ],
            'description'   => ['nullable', 'string', 'max:500'],
            'price_mwk'     => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:366'],
            'grace_days'    => ['required', 'integer', 'min:0', 'max:30'],
            'is_active'     => ['boolean'],
            'features'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function updatedName(string $value): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $this->editingId     = $plan->id;
        $this->name          = $plan->name;
        $this->slug          = $plan->slug;
        $this->description   = (string) $plan->description;
        $this->price_mwk     = (string) $plan->price_mwk;
        $this->duration_days = $plan->duration_days;
        $this->grace_days    = $plan->grace_days;
        $this->is_active     = $plan->is_active;
        $this->features      = implode("\n", $plan->features ?? []);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['description'] = $data['description'] ?: null;
        $data['features']    = collect(preg_split('/\R/', (string) $data['features']))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        SubscriptionPlan::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->success($this->editingId ? 'Plan updated.' : 'Plan created.');
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        $plan->is_active
            ? $this->success("{$plan->name} is now offered to subscribers.")
            : $this->warning("{$plan->name} hidden from new subscribers. Existing subscriptions are unaffected.");
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'slug', 'description', 'price_mwk', 'duration_days', 'grace_days', 'is_active', 'features');
        $this->resetValidation();
    }

    public function render()
    {
        $plans = SubscriptionPlan::query()
            ->withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')->where('ends_at', '>', now())])
            ->orderBy('duration_days')
            ->orderBy('price_mwk')
            ->get();

        return view('livewire.admin.subscription-plans', [
            'plans' => $plans,
        ])->layout('components.admin-layout');
    }
}
