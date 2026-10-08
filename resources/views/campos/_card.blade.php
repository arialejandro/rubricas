{{-- Tarjeta de un campo formativo en el trimestre. Requiere $book, $campo, $group. --}}
@php
    $p = $book->campoProgress($campo);
    $avg = $book->campoAverage($campo);
    $configured = $book->isConfigured($campo);
@endphp
<a href="{{ $configured ? route('campos.show', [$group, $campo]) : route('campos.edit', [$group, $campo]) }}"
   class="tile min-h-40 overflow-hidden" style="--c: {{ \App\Support\Campos::color($campo) }}">
    <span class="campo-bar absolute inset-x-0 top-0 h-1.5"></span>
    <div class="flex items-start justify-between gap-2 pt-1">
        <h3 class="text-lg font-bold leading-snug">{{ \App\Support\Campos::name($campo) }}</h3>
        @if ($configured && $avg !== null)
            <span class="text-right">
                <span class="lvl-text block text-2xl font-bold tabular-nums leading-none" data-level="{{ \App\Support\Level::of($avg) }}">{{ \App\Support\TermBook::fmt($avg) }}</span>
                <span class="text-xs text-ink-muted">promedio</span>
            </span>
        @endif
    </div>
    @if (! $configured)
        <p class="flex items-center gap-2 text-sm font-semibold text-primary"><x-icon name="settings" class="size-4" /> Definir aspectos del trimestre</p>
    @else
        <p class="text-sm text-ink-muted">
            {{ $book->aspectsFor($campo)->pluck('name')->implode(' · ') }}
            @if ($book->subjectsFor($campo)->isNotEmpty())
                <span class="block">+ {{ $book->subjectsFor($campo)->pluck('name')->implode(', ') }}</span>
            @endif
        </p>
        <div class="mt-auto">
            <div class="mb-1 flex justify-between text-xs">
                <span class="font-semibold {{ $p['missing'] ? 'text-pending' : 'text-done' }}">{{ $p['missing'] ? $p['missing'].' pendientes' : 'Completo' }}</span>
                <span class="tabular-nums text-ink-muted" data-paint="campo-progress:{{ $campo }}">{{ $p['progress'] }}%</span>
            </div>
            <x-progress :value="$p['progress']" bar="campo:{{ $campo }}" />
        </div>
    @endif
</a>
