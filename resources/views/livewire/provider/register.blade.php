<div class="flex flex-col gap-7">

    {{-- Heading --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Apply as a Content Provider</h1>
        <p class="text-sm text-base-content/50 mt-1">Step {{ $step }} of 3</p>
    </div>

    {{-- Step progress --}}
    <ul class="steps w-full text-xs">
        <li class="step {{ $step >= 1 ? 'step-primary' : '' }}">Account</li>
        <li class="step {{ $step >= 2 ? 'step-primary' : '' }}">Profile</li>
        <li class="step {{ $step >= 3 ? 'step-primary' : '' }}">Channel</li>
    </ul>

    {{-- Step 1: Account --}}
    @if($step === 1)
        <div class="flex flex-col gap-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="form-control sm:col-span-2">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Full name <span class="text-error">*</span></span></label>
                    <input wire:model="name" type="text" placeholder="Jane Banda" autocomplete="name"
                        class="input input-bordered w-full focus:input-primary @error('name') input-error @enderror" />
                    @error('name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
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
                        class="input input-bordered w-full focus:input-primary @error('password_confirmation') input-error @enderror" />
                    @error('password_confirmation') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <button wire:click="nextStep" class="btn btn-primary w-full mt-2" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="nextStep">Continue</span>
                <span wire:loading wire:target="nextStep" class="loading loading-spinner loading-sm"></span>
                <x-icon name="o-arrow-right" class="w-4 h-4" wire:loading.remove wire:target="nextStep" />
            </button>
        </div>
    @endif

    {{-- Step 2: Organisation profile --}}
    @if($step === 2)
        <div class="flex flex-col gap-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="form-control sm:col-span-2">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Organisation / Company name <span class="text-error">*</span></span></label>
                    <input wire:model="company_name" type="text" placeholder="Faith Broadcasting Network"
                        class="input input-bordered w-full focus:input-primary @error('company_name') input-error @enderror" />
                    @error('company_name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Primary contact person <span class="text-error">*</span></span></label>
                    <input wire:model="contact_person" type="text" placeholder="Jane Banda"
                        class="input input-bordered w-full focus:input-primary @error('contact_person') input-error @enderror" />
                    @error('contact_person') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Contact phone <span class="text-error">*</span></span></label>
                    <input wire:model="contact_phone" type="tel" placeholder="+265 999 000 000"
                        class="input input-bordered w-full focus:input-primary @error('contact_phone') input-error @enderror" />
                    @error('contact_phone') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="form-control sm:col-span-2">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">About your organisation</span></label>
                    <textarea wire:model="description" rows="3" placeholder="Brief description of your content and audience…"
                        class="textarea textarea-bordered w-full focus:textarea-primary resize-none @error('description') textarea-error @enderror"></textarea>
                    @error('description') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button wire:click="prevStep" class="btn btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4" /> Back
                </button>
                <button wire:click="nextStep" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="nextStep">Continue</span>
                    <span wire:loading wire:target="nextStep" class="loading loading-spinner loading-sm"></span>
                    <x-icon name="o-arrow-right" class="w-4 h-4" wire:loading.remove wire:target="nextStep" />
                </button>
            </div>
        </div>
    @endif

    {{-- Step 3: Channel details --}}
    @if($step === 3)
        <div class="flex flex-col gap-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="form-control sm:col-span-2">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Channel name <span class="text-error">*</span></span></label>
                    <input wire:model="channel_name" type="text" placeholder="Faith TV"
                        class="input input-bordered w-full focus:input-primary @error('channel_name') input-error @enderror" />
                    @error('channel_name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Category</span></label>
                    <select wire:model="channel_category" class="select select-bordered w-full focus:select-primary @error('channel_category') select-error @enderror">
                        <option value="">Select a category</option>
                        <option value="church">Church / Worship</option>
                        <option value="education">Education</option>
                        <option value="news">News & Current Affairs</option>
                        <option value="music">Music</option>
                        <option value="sports">Sports</option>
                        <option value="children">Children</option>
                        <option value="documentary">Documentary</option>
                        <option value="general">General Entertainment</option>
                    </select>
                    @error('channel_category') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium text-sm">Primary language <span class="text-error">*</span></span></label>
                    <select wire:model="channel_language" class="select select-bordered w-full focus:select-primary @error('channel_language') select-error @enderror">
                        <option value="en">English</option>
                        <option value="ny">Chichewa</option>
                        <option value="tu">Tumbuka</option>
                        <option value="yo">Yao</option>
                        <option value="sw">Swahili</option>
                        <option value="fr">French</option>
                    </select>
                    @error('channel_language') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="alert bg-base-200 border-base-300 text-sm mt-1">
                <x-icon name="o-information-circle" class="w-5 h-5 shrink-0 text-info" />
                <span class="text-base-content/70">Your application will be reviewed by our team. Stream credentials are issued after approval.</span>
            </div>

            <div class="flex gap-3 mt-1">
                <button wire:click="prevStep" class="btn btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4" /> Back
                </button>
                <button wire:click="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Submit Application</span>
                    <span wire:loading wire:target="submit" class="loading loading-spinner loading-sm"></span>
                    <x-icon name="o-paper-airplane" class="w-4 h-4" wire:loading.remove wire:target="submit" />
                </button>
            </div>
        </div>
    @endif

    <p class="text-center text-sm text-base-content/50">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="link link-primary font-medium">Log in</a>
    </p>

</div>
