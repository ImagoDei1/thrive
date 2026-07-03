<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' — Thrive' : 'Thrive' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased bg-base-100">

<div class="min-h-screen lg:grid lg:grid-cols-2">

    {{-- Left panel — branding --}}
    <div class="relative hidden lg:flex flex-col justify-between bg-neutral-950 p-12 overflow-hidden">

        {{-- Background gradient orbs --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-primary/20 blur-3xl"></div>
            <div class="absolute top-1/2 -right-24 w-80 h-80 rounded-full bg-secondary/15 blur-3xl"></div>
            <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-primary/10 blur-3xl"></div>
        </div>

        {{-- Logo --}}
        <div class="relative z-10 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
                <x-icon name="o-signal" class="w-6 h-6 text-white" />
            </div>
            <span class="text-white font-black text-2xl tracking-tight">Thrive</span>
        </div>

        {{-- Hero copy --}}
        <div class="relative z-10 space-y-8">
            <div>
                <h1 class="text-4xl font-black text-white leading-tight">
                    Stream to<br>
                    <span class="text-primary">thousands.</span><br>
                    Get paid.
                </h1>
                <p class="mt-4 text-neutral-400 text-lg leading-relaxed max-w-xs">
                    One platform for churches, broadcasters, schools, and NGOs across Malawi to reach their audience.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-play-circle" class="w-4 h-4 text-primary" />
                    </div>
                    <span class="text-neutral-300 text-sm">Live & on-demand streaming via RTMP / HLS</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-banknotes" class="w-4 h-4 text-primary" />
                    </div>
                    <span class="text-neutral-300 text-sm">80 % of subscriber revenue — paid via mobile money</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-chart-bar" class="w-4 h-4 text-primary" />
                    </div>
                    <span class="text-neutral-300 text-sm">Real-time watch-time analytics per billing period</span>
                </div>
            </div>
        </div>

        {{-- Footer note --}}
        <p class="relative z-10 text-neutral-600 text-xs">
            &copy; {{ date('Y') }} Thrive Streaming. Malawi.
        </p>
    </div>

    {{-- Right panel — form --}}
    <div class="flex flex-col justify-center items-center px-6 py-12 lg:px-16 bg-base-100">

        {{-- Mobile logo --}}
        <div class="flex items-center gap-2 mb-10 lg:hidden">
            <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center">
                <x-icon name="o-signal" class="w-5 h-5 text-white" />
            </div>
            <span class="font-black text-2xl tracking-tight">Thrive</span>
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>

</div>

@fluxScripts
</body>
</html>
