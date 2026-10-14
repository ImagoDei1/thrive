<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - Thrive Admin' : 'Thrive Admin' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">

<x-nav sticky class="lg:hidden">
    <x-slot:brand>
        <div class="flex items-center gap-2 ml-3">
            <x-icon name="o-signal" class="w-6 h-6 text-primary" />
            <span class="font-black text-lg">Thrive</span>
        </div>
    </x-slot:brand>
    <x-slot:actions>
        <label for="main-drawer" class="lg:hidden mr-3">
            <x-icon name="o-bars-2" class="cursor-pointer" />
        </label>
    </x-slot:actions>
</x-nav>

<x-main>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

        {{-- Brand --}}
        <div class="flex items-center gap-2 p-5 pt-3">
            <x-icon name="o-signal" class="w-6 h-6 text-primary" />
            <span class="font-black text-xl tracking-tight">Thrive</span>
            <span class="text-xs font-semibold text-base-content/40 mt-0.5">Admin</span>
        </div>

        <x-menu activate-by-route>
            {{-- User --}}
            @if($user = auth()->user())
                <x-menu-separator />
                <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                    <x-slot:actions>
                        <x-dropdown>
                            <x-slot:trigger>
                                <x-button icon="o-cog-6-tooth" class="btn-circle btn-ghost btn-xs" />
                            </x-slot:trigger>
                            <x-menu-item icon="o-user" label="Profile" link="{{ route('settings.profile') }}" wire:navigate />
                            <form method="POST" action="{{ route('logout') }}" id="admin-logout-form">@csrf</form>
                            <x-menu-item icon="o-arrow-right-on-rectangle" label="Logout" no-wire-navigate
                                @click.prevent="document.getElementById('admin-logout-form').submit()" />
                        </x-dropdown>
                    </x-slot:actions>
                </x-list-item>
            @endif

            <x-menu-separator />

            <x-menu-item title="Dashboard" icon="o-chart-pie" link="{{ route('admin.dashboard') }}" wire:navigate />

            <x-menu-separator />

            <x-menu-item title="Providers" icon="o-building-office-2" link="{{ route('admin.providers.index') }}" wire:navigate />
            <x-menu-item title="Channels" icon="o-tv" link="{{ route('admin.channels.index') }}" wire:navigate />
            <x-menu-item title="Content" icon="o-film" link="{{ route('admin.content.index') }}" wire:navigate />
            <x-menu-item title="Change Requests" icon="o-pencil-square" link="{{ route('admin.change-requests.index') }}" wire:navigate />

            <x-menu-separator />

            <x-menu-item title="Subscribers" icon="o-users" link="{{ route('admin.subscribers.index') }}" wire:navigate />

            <x-menu-separator />

            <x-menu-item title="Billing Periods" icon="o-calendar-days" link="{{ route('admin.billing.index') }}" wire:navigate />
            <x-menu-item title="Payouts" icon="o-banknotes" link="{{ route('admin.payouts.index') }}" wire:navigate />
        </x-menu>
    </x-slot:sidebar>

    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>

<x-toast />
<x-theme-toggle class="hidden" />
</body>
</html>
