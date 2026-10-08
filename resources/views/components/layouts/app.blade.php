<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e3a5f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @auth
        <header class="sticky top-0 z-30 bg-brand-900 text-white shadow">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-bold">
                    <span class="grid size-8 place-items-center rounded-lg bg-white/15">✓</span>
                    {{ config('app.name') }}
                </a>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-white/80 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg px-3 py-2 text-white/90 hover:bg-white/10">Salir</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-7xl px-4 py-5 pb-24 sm:py-8">
        @isset($breadcrumbs)
            <nav class="mb-3 flex flex-wrap items-center gap-1 text-sm text-slate-500">
                {{ $breadcrumbs }}
            </nav>
        @endisset

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    <div id="toast" class="fixed inset-x-4 bottom-4 z-50 mx-auto hidden max-w-md rounded-xl bg-slate-900 px-4 py-3 text-center text-sm text-white shadow-lg" role="status"></div>
</body>
</html>
