<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6d28d9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    {{-- Aplica el tema guardado antes de pintar para que no "parpadee" en claro. --}}
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">Ir al contenido</a>

    @auth
        @php
            $nav = isset($navGroup) && $navGroup ? [
                ['route' => route('grupos.show', $navGroup), 'active' => request()->routeIs('grupos.show'), 'icon' => 'home', 'label' => 'Inicio'],
                ['route' => route('projects.index', $navGroup), 'active' => request()->routeIs('projects.*', 'capture'), 'icon' => 'folder', 'label' => 'Proyectos'],
                ['route' => route('students.index', $navGroup), 'active' => request()->routeIs('students.*'), 'icon' => 'users', 'label' => 'Alumnos'],
                ['route' => route('export.group', $navGroup), 'active' => false, 'icon' => 'sheet', 'label' => 'Excel'],
            ] : [];
        @endphp

        <header class="sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-2.5 sm:gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-bold" aria-label="Inicio">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="size-10 rounded-xl" width="40" height="40">
                    <span class="hidden leading-tight sm:block">
                        {{ config('app.name') }}
                        @if (isset($navGroup) && $navGroup)
                            <span class="block text-xs font-medium text-ink-muted">{{ $navGroup->label() }} · {{ $navGroup->shiftLabel() }}</span>
                        @endif
                    </span>
                </a>

                @if ($nav)
                    <nav class="ml-4 hidden items-center gap-1 lg:flex" aria-label="Principal">
                        @foreach ($nav as $item)
                            <a href="{{ $item['route'] }}" @class(['flex min-h-11 items-center gap-2 rounded-xl px-3.5 text-sm font-semibold transition',
                                'bg-primary-soft text-primary' => $item['active'], 'text-ink-muted hover:bg-surface-2 hover:text-ink' => ! $item['active']])
                               @if ($item['active']) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    {{-- Selector de turno: cada turno es un grupo; el que no existe ofrece crearlo. --}}
                    @if (isset($navGroups) && $navGroups->isNotEmpty())
                        <x-shift-switch :groups="$navGroups" :current="$navGroup" />
                    @endif

                    <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Cambiar modo claro u oscuro">
                        <x-icon name="moon" class="dark:hidden" />
                        <x-icon name="sun" class="hidden dark:block" />
                    </button>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-icon" aria-label="Cerrar sesión" title="Cerrar sesión ({{ auth()->user()->name }})"><x-icon name="logout" /></button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main id="main" @class(['mx-auto max-w-7xl px-4 py-5 sm:py-7', 'pb-28 lg:pb-10' => ! empty($nav)])>
        @isset($breadcrumbs)
            <nav class="mb-3 flex flex-wrap items-center gap-1.5 text-sm text-ink-muted" aria-label="Ruta">
                {{ $breadcrumbs }}
            </nav>
        @endisset

        @if (session('status'))
            <div class="mb-4 flex items-start gap-2 rounded-xl border border-done/30 bg-done-soft px-4 py-3 text-sm font-medium text-done" role="status">
                <x-icon name="check" class="mt-0.5 size-4" /> {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p class="flex items-start gap-2"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{ $slot }}
    </main>

    @if (! empty($nav))
        {{-- Pestañas inferiores en teléfono/iPad: el pulgar llega sin estirarse. --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Principal">
            <div class="mx-auto grid max-w-xl grid-cols-4">
                @foreach ($nav as $item)
                    <a href="{{ $item['route'] }}" @class(['flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-semibold',
                        'text-primary' => $item['active'], 'text-ink-muted' => ! $item['active']])
                       @if ($item['active']) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="size-6" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <div id="toast" class="fixed inset-x-4 bottom-24 z-50 mx-auto hidden max-w-md rounded-xl bg-ink px-4 py-3 text-center text-sm font-medium text-canvas shadow-lg lg:bottom-6" role="status" aria-live="polite"></div>

    {{ $after ?? '' }}
</body>
</html>
