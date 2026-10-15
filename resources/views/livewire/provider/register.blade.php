<div class="flex flex-col gap-7">

    {{-- Heading --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Apply as a Content Provider</h1>
        <p class="text-sm text-base-content/50 mt-1">Create your account — you'll set up your channel and verify your organisation from inside the portal.</p>
    </div>

    <form wire:submit="submit" class="flex flex-col gap-4">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="form-control sm:col-span-2">
                <label class="label pb-1"><span class="label-text font-medium text-sm">Your full name <span class="text-error">*</span></span></label>
                <input wire:model="name" type="text" placeholder="Jane Banda" autocomplete="name"
                    class="input input-bordered w-full focus:input-primary @error('name') input-error @enderror" />
                @error('name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="form-control sm:col-span-2">
                <label class="label pb-1"><span class="label-text font-medium text-sm">Organisation name <span class="text-error">*</span></span></label>
                <input wire:model="company_name" type="text" placeholder="Faith Broadcasting Network" autocomplete="organization"
                    class="input input-bordered w-full focus:input-primary @error('company_name') input-error @enderror" />
                @error('company_name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="form-control sm:col-span-2">
                <label class="label pb-1"><span class="label-text font-medium text-sm">Email address <span class="text-error">*</span></span></label>
                <input wire:model="email" type="email" placeholder="jane@example.com" autocomplete="email"
                    class="input input-bordered w-full focus:input-primary @error('email') input-error @enderror" />
                @error('email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="form-control">
                <label class="label pb-1"><span class="label-text font-medium text-sm">Password <span class="text-error">*</span></span></label>
                <input wire:model="password" type="password" placeholder="••••••••" autocomplete="new-password"
                    class="input input-bordered w-full focus:input-primary @error('password') input-error @enderror" />
                @error('password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="form-control">
                <label class="label pb-1"><span class="label-text font-medium text-sm">Confirm password <span class="text-error">*</span></span></label>
                <input wire:model="password_confirmation" type="password" placeholder="••••••••" autocomplete="new-password"
                    class="input input-bordered w-full focus:input-primary" />
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-full mt-2" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Create account</span>
            <span wire:loading wire:target="submit" class="loading loading-spinner loading-sm"></span>
            <x-icon name="o-arrow-right" class="w-4 h-4" wire:loading.remove wire:target="submit" />
        </button>
    </form>

    <p class="text-center text-sm text-base-content/50">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="link link-primary font-medium">Log in</a>
    </p>

</div>
