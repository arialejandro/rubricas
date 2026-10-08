@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
<x-layouts.app title="{{ $criterion->name }} · {{ $project->name }}">
    <x-slot:breadcrumbs>
        <a href="{{ route('grupos.show', $group) }}" class="hover:text-brand-700">{{ $group->name }}</a>
        <span>/</span><a href="{{ route('projects.show', [$group, $project]) }}" class="hover:text-brand-700">{{ $project->name }}</a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-2xl">
        <div class="sticky top-[60px] z-20 -mx-4 mb-4 border-b border-slate-200 bg-slate-100/95 px-4 pt-1 pb-3 backdrop-blur">
            <div class="mb-2 flex items-center justify-between gap-2">
                @if ($prev)
                    <a href="{{ route('capture', [$group, $project, $prev]) }}" class="btn btn-ghost px-3" aria-label="Aspecto anterior">‹</a>
                @else
                    <span class="w-11"></span>
                @endif
                <div class="text-center">
                    <h1 class="text-xl font-bold leading-tight">{{ $criterion->name }}</h1>
                    <p class="text-sm text-slate-500">vale {{ $fmt($criterion->weight) }}% · <span data-criterion-missing="{{ $criterion->id }}">{{ ($m = $book->missingForCriterion($criterion)) ? "{$m} sin calificar" : '✓' }}</span></p>
                </div>
                @if ($next)
                    <a href="{{ route('capture', [$group, $project, $next]) }}" class="btn btn-ghost px-3" aria-label="Siguiente aspecto">›</a>
                @else
                    <span class="w-11"></span>
                @endif
            </div>
            <x-progress :graded="$book->gradedCells()" :total="$book->totalCells()" live />
            <div class="mt-1 flex justify-between text-xs text-slate-500">
                <span>Proyecto: <span data-project-progress>{{ $book->progress() }}%</span></span>
                <label class="flex items-center gap-1.5"><input type="checkbox" class="size-4" data-filter-pending-criterion> Solo sin calificar</label>
            </div>
        </div>

        <ul class="card divide-y divide-slate-100" data-grades-url="{{ route('grades.update', [$group, $project]) }}">
            @foreach ($book->students as $s)
                @php($score = $book->score($s->id, $criterion->id))
                <li class="flex items-center gap-3 px-4 py-2.5" data-capture-row data-has-score="{{ $score === null ? 0 : 1 }}">
                    <span class="w-6 text-right text-sm tabular-nums text-slate-400">{{ $s->list_number }}</span>
                    <span class="flex-1 font-medium">{{ $s->name }}</span>
                    <input class="score-input w-20 py-2.5 text-lg" inputmode="decimal" autocomplete="off" enterkeyhint="next"
                           value="{{ $score === null ? '' : $fmt($score) }}"
                           data-student="{{ $s->id }}" data-criterion="{{ $criterion->id }}"
                           aria-label="{{ $s->name }}">
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex justify-between gap-2">
            <a href="{{ route('projects.show', [$group, $project]) }}" class="btn btn-ghost">Ver matriz completa</a>
            @if ($next)
                <a href="{{ route('capture', [$group, $project, $next]) }}" class="btn btn-primary">Siguiente: {{ $next->name }} ›</a>
            @endif
        </div>
    </div>
</x-layouts.app>
