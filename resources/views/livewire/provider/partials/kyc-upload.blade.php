{{-- Expects: $type, $label, $required, $existing (?ProviderKycDocument), $upload (?TemporaryUploadedFile), optional $hint --}}
<div class="form-control sm:col-span-1">
    <label class="label pb-1">
        <span class="label-text font-medium text-sm">
            {{ $label }} @if($required)<span class="text-error">*</span>@endif
        </span>
    </label>

    <label @class([
        'flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed p-5 text-center cursor-pointer transition-colors',
        'border-error' => $errors->has($type),
        'border-success/60 bg-success/5' => ! $errors->has($type) && ($upload || $existing),
        'border-base-300 hover:border-primary' => ! $errors->has($type) && ! $upload && ! $existing,
    ])>
        <input type="file" wire:model="{{ $type }}" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />

        <div wire:loading wire:target="{{ $type }}" class="loading loading-spinner loading-sm"></div>

        <div wire:loading.remove wire:target="{{ $type }}" class="flex flex-col items-center gap-1">
            @if($upload)
                <x-icon name="o-document-check" class="w-6 h-6 text-success" />
                <span class="text-sm font-medium truncate max-w-full">{{ $upload->getClientOriginalName() }}</span>
                <span class="text-xs text-base-content/50">Ready to save · click to change</span>
            @elseif($existing)
                <x-icon name="o-check-circle" class="w-6 h-6 text-success" />
                <span class="text-sm font-medium truncate max-w-full">{{ $existing->original_name }}</span>
                <span class="text-xs text-base-content/50">Uploaded · click to replace</span>
            @else
                <x-icon name="o-arrow-up-tray" class="w-6 h-6 text-base-content/40" />
                <span class="text-sm">Click to upload</span>
                <span class="text-xs text-base-content/40">PDF, JPG or PNG up to 5 MB</span>
            @endif
        </div>
    </label>

    @isset($hint)
        <span class="text-xs text-base-content/50 mt-1">{{ $hint }}</span>
    @endisset
    @error($type) <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
</div>
