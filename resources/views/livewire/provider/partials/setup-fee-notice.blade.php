{{-- Explains the reduced revenue share that replaces the upfront setup fee. --}}
@php
    $feeStatus = $provider->setupFeeStatus();
@endphp

@if(in_array($feeStatus, ['active', 'upcoming'], true))
    @php
        $points       = (float) $provider->setup_fee_percentage;
        $basePercent  = 100 - (float) (\App\Models\BillingPeriod::latest('starts_at')->value('platform_percentage') ?? 20);
        $fmt          = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
        $fmtPeriod    = fn (string $label) => \Carbon\Carbon::createFromFormat('!Y-m', $label)->format('F Y');
    @endphp
    <x-alert icon="o-information-circle" class="alert-info mb-8">
        <div>
            <p class="font-semibold">No setup fee — reduced revenue share until {{ $fmtPeriod($provider->setup_fee_ends_period) }}</p>
            <p class="text-sm">
                From {{ $fmtPeriod($provider->setup_fee_starts_period) }} to {{ $fmtPeriod($provider->setup_fee_ends_period) }}
                you receive {{ $fmt($basePercent - $points) }}% of the subscriber revenue earned by your watch time instead of
                {{ $fmt($basePercent) }}%. The {{ $fmt($points) }} percentage points cover your setup. After that you receive the full {{ $fmt($basePercent) }}%.
            </p>
        </div>
    </x-alert>
@endif
