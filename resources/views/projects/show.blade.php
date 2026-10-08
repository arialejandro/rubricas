@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
<x-layouts.app :title="$project->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('projects.index', $group) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Proyectos</a>
    </x-slot:breadcrumbs>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $project->name }}</h1>
            <p class="text-ink-muted">
                @if ($project->due_date) {{ $project->due_date->translatedFormat('j \d\e F') }} · @endif
                {{ $book->students->count() }} alumnos
                @if ($project->description) · {{ $project->description }} @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('projects.edit', [$group, $project]) }}" class="btn btn-ghost"><x-icon name="pencil" class="size-4" /> Rúbrica</a>
            <a href="{{ route('export.project', [$group, $project]) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Excel</a>
        </div>
    </div>

    @if ($book->students->isEmpty())
        <div class="card flex flex-wrap items-center gap-3 p-5">
            <p class="flex-1 font-semibold">El grupo no tiene alumnos activos.</p>
            <a href="{{ route('students.index', $group) }}" class="btn btn-primary">Cargar lista</a>
        </div>
    @else
        <section class="card mb-5 p-4 sm:p-5">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <p class="font-semibold"><span class="num" data-project-graded="{{ $project->id }}">{{ $book->gradedCells() }}</span> de <span class="num">{{ $book->totalCells() }}</span> calificaciones</p>
                <p class="text-sm"><span class="num font-bold text-pending" data-project-missing="{{ $project->id }}">{{ $book->missingCells() }}</span> <span class="text-ink-muted">pendientes ·</span> <span class="num font-bold" data-project-progress="{{ $project->id }}">{{ $book->progress() }}%</span></p>
            </div>
            <x-progress :value="$book->progress()" :project="$project->id" />
        </section>

        {{-- Aspectos: un tap y a capturar --}}
        <h2 class="mb-3 text-lg font-bold">Calificar por aspecto</h2>
        <div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($book->criteria as $c)
                @php($m = $book->missingForCriterion($c))
                @php($done = $book->students->count() - $m)
                <a href="{{ route('capture', [$group, $project, $c]) }}" class="tile min-h-32">
                    <div class="flex items-start justify-between gap-2">
                        <span class="num rounded-lg bg-surface-2 px-2 py-1 text-sm font-bold">{{ $fmt($c->weight) }}%</span>
                        <x-icon name="chevron-right" class="size-5 text-ink-muted" />
                    </div>
                    <h3 class="line-clamp-2 text-lg font-bold leading-snug">{{ $c->name }}</h3>
                    <div class="mt-auto">
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="font-semibold {{ $m ? 'text-pending' : 'text-done' }}" data-criterion-missing="{{ $c->id }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                            <span class="num text-ink-muted" data-criterion-count="{{ $c->id }}">{{ $done }}/{{ $book->students->count() }}</span>
                        </div>
                        <x-progress :value="(int) floor($done * 100 / max(1, $book->students->count()))" :criterion="$c->id" />
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Matriz completa: cada casilla abre el teclado; avanza hacia abajo por el mismo aspecto --}}
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-bold">Todas las calificaciones</h2>
        </div>
        <x-search class="mb-3" pending-toggle="Solo pendientes" />

        <section class="card overflow-x-auto" data-grades-url="{{ route('grades.update', [$group, $project]) }}" data-advance="column">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-ink-muted">
                        <th class="sticky left-0 z-10 min-w-52 bg-surface px-4 py-3 font-semibold">Alumno</th>
                        @foreach ($book->criteria as $c)
                            <th class="px-2 py-3 text-center font-semibold">
                                <span class="block max-w-28 truncate text-ink" title="{{ $c->name }}">{{ $c->name }}</span>
                                <span class="num text-xs font-normal">{{ $fmt($c->weight) }}%</span>
                            </th>
                        @endforeach
                        <th class="px-2 py-3 text-center font-semibold">%</th>
                        <th class="px-2 py-3 text-center font-semibold text-ink">Final</th>
                        <th class="px-4 py-3 text-center font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($book->students as $s)
                        @php($complete = $book->isComplete($s))
                        @php($key = $project->id.':'.$s->id)
                        <tr data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}"
                            data-done="{{ $complete ? 1 : 0 }}" data-row-done="{{ $key }}">
                            <td class="sticky left-0 z-10 bg-surface px-4 py-2">
                                <span class="num mr-1 inline-block w-6 text-right text-xs text-ink-muted">{{ $s->list_number }}</span>
                                <a href="{{ route('students.show', [$group, $s]) }}" class="font-medium hover:text-primary">{{ $s->name }}</a>
                            </td>
                            @foreach ($book->criteria as $c)
                                <td class="px-2 py-1.5 text-center"><x-score :student="$s" :criterion="$c" :value="$book->score($s->id, $c->id)" /></td>
                            @endforeach
                            <td class="num px-2 text-center text-ink-muted" data-percent="{{ $key }}">{{ $fmt($book->finalPercent($s)) }}%</td>
                            <td class="num px-2 text-center text-lg font-bold {{ $complete ? '' : 'opacity-40' }}" data-final="{{ $key }}" title="{{ $complete ? '' : 'Provisional: faltan aspectos' }}">{{ $fmt($book->finalScore($s)) }}</td>
                            <td class="px-4 text-center">
                                <span class="chip {{ $complete ? 'chip-done' : 'chip-pending' }}" data-status="{{ $key }}">{{ $complete ? 'Completo' : 'Faltan '.$book->missingFor($s)->count() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
        </section>
        <p class="mt-2 text-xs text-ink-muted">Toca una casilla para calificar. Una casilla con guion está pendiente; el 0 sí cuenta como calificación.</p>
    @endif

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
