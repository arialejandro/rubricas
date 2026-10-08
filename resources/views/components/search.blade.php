{{-- Buscador de alumnos (resources/js/search.js): apellido, nombre o número de lista. --}}
@props(['placeholder' => 'Buscar por apellido, nombre o N.L.', 'reveal' => false, 'pendingToggle' => null])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <label class="relative min-w-0 flex-1">
        <span class="sr-only">Buscar alumno</span>
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-muted" />
        <input type="search" class="input min-h-14 rounded-2xl pl-12 text-lg" placeholder="{{ $placeholder }}"
               data-search @if ($reveal) data-search-reveal @endif autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go">
    </label>
    @if ($pendingToggle)
        <label class="flex min-h-14 items-center gap-2 rounded-2xl border border-line bg-surface px-4 text-sm font-semibold has-checked:border-pending has-checked:bg-pending-soft has-checked:text-pending">
            <input type="checkbox" class="size-5 accent-[var(--pending)]" data-pending-toggle> {{ $pendingToggle }}
        </label>
    @endif
</div>
