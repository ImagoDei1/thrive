<div class="flex flex-col items-center gap-6 text-center py-4">

    <div class="flex items-center justify-center gap-2 mb-2">
        <x-icon name="o-signal" class="w-7 h-7 text-primary" />
        <span class="font-black text-2xl tracking-tight">Thrive</span>
    </div>

    <div class="w-20 h-20 rounded-full bg-warning/10 flex items-center justify-center">
        <x-icon name="o-clock" class="w-10 h-10 text-warning" />
    </div>

    <div>
        <h1 class="text-2xl font-bold">Application Under Review</h1>
        <p class="text-base-content/60 mt-2 max-w-sm">
            Thank you, <strong>{{ $provider?->contact_person ?? auth()->user()->name }}</strong>.
            Your provider application for <strong>{{ $provider?->company_name }}</strong> has been submitted
            and is being reviewed by our team.
        </p>
    </div>

    <x-card class="w-full max-w-sm text-left">
        <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3">
                <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                <span>Account created</span>
            </div>
            <div class="flex items-center gap-3">
                <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                <span>Organisation profile submitted</span>
            </div>
            <div class="flex items-center gap-3">
                <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                <span>Channel details submitted</span>
            </div>
            <div class="flex items-center gap-3">
                <x-icon name="o-clock" class="w-5 h-5 text-warning shrink-0" />
                <span class="text-base-content/60">Awaiting admin approval</span>
            </div>
        </div>
    </x-card>

    <p class="text-sm text-base-content/50 max-w-xs">
        We'll notify you by email at <strong>{{ auth()->user()->email }}</strong> once your account is approved.
        This usually takes 1–2 business days.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button label="Log out" icon="o-arrow-right-on-rectangle" class="btn-ghost btn-sm" type="submit" />
    </form>
</div>
