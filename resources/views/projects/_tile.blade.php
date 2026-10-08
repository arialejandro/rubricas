{{-- Tarjeta de proyecto. $s = GroupOverview::projectSummary() --}}
<a href="{{ route('projects.show', [$project->group_id, $project]) }}" class="tile min-h-36">
    <div class="flex items-start justify-between gap-2">
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $s['total'] && ! $s['missing'] ? 'bg-done-soft text-done' : 'bg-primary-soft text-primary' }}">
            <x-icon :name="$s['total'] && ! $s['missing'] ? 'check' : 'folder'" />
        </span>
        @if ($s['total'] && $s['missing'])
            <span class="chip chip-pending">Faltan {{ $s['missing'] }}</span>
        @elseif ($s['total'])
            <span class="chip chip-done">Completo</span>
        @endif
    </div>
    <div class="min-w-0">
        <h3 class="line-clamp-2 font-bold leading-snug">{{ $project->name }}</h3>
        <p class="mt-0.5 text-sm text-ink-muted">
            {{ $project->criteria->count() }} {{ $project->criteria->count() === 1 ? 'aspecto' : 'aspectos' }}
            @if ($project->due_date) · {{ $project->due_date->translatedFormat('j M') }} @endif
        </p>
    </div>
    <div class="mt-auto">
        <div class="mb-1 flex justify-between text-xs text-ink-muted">
            <span class="num">{{ $s['graded'] }}/{{ $s['total'] }}</span>
            <span class="num font-semibold">{{ $s['progress'] }}%</span>
        </div>
        <x-progress :value="$s['progress']" />
    </div>
</a>
