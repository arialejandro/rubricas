{{-- Tarjeta de proyecto. Requiere $book, $project, $group. --}}
@php($s = $book->projectProgress($project))
<a href="{{ route('projects.show', [$group, $project]) }}" class="tile min-h-40 overflow-hidden" style="--c: {{ \App\Support\Campos::color($project->campo) }}">
    <span class="campo-bar absolute inset-x-0 top-0 h-1.5"></span>
    <div class="flex flex-wrap items-start justify-between gap-2 pt-1">
        <div class="flex flex-wrap gap-1">
            @foreach ($project->campos() as $c)
                <x-campo-chip :campo="$c" />
            @endforeach
        </div>
        @if ($s['total'] && $s['missing'])
            <span class="chip chip-pending">Faltan {{ $s['missing'] }}</span>
        @elseif ($s['total'])
            <span class="chip chip-done">Completo</span>
        @endif
    </div>
    <div class="min-w-0">
        <h3 class="line-clamp-2 font-bold leading-snug">{{ $project->name }}</h3>
        <p class="mt-0.5 text-sm text-ink-muted">
            {{ $project->products->count() }} {{ $project->products->count() === 1 ? 'producto' : 'productos' }}
            @if ($project->due_date) · {{ $project->due_date->translatedFormat('j M') }} @endif
        </p>
    </div>
    <div class="mt-auto">
        @if ($s['total'])
            <div class="mb-1 flex justify-between text-xs text-ink-muted">
                <span class="tabular-nums">{{ $s['graded'] }}/{{ $s['total'] }}</span>
                <span class="font-semibold tabular-nums">{{ $s['progress'] }}%</span>
            </div>
            <x-progress :value="$s['progress']" />
        @else
            <p class="text-xs font-semibold text-primary">Agregar productos y criterios</p>
        @endif
    </div>
</a>
