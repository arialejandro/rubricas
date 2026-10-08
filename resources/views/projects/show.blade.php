@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
<x-layouts.app :title="$project->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('dashboard') }}" class="hover:text-brand-700">Mis grupos</a>
        <span>/</span><a href="{{ route('grupos.show', $group) }}" class="hover:text-brand-700">{{ $group->name }}</a>
    </x-slot:breadcrumbs>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $project->name }}</h1>
            <p class="text-sm text-slate-500">
                @if ($project->due_date) {{ $project->due_date->translatedFormat('j \d\e F Y') }} · @endif
                {{ $book->criteria->count() }} {{ Str::plural('aspecto', $book->criteria->count()) }} · {{ $book->students->count() }} alumnos
            </p>
            @if ($project->description)
                <p class="mt-1 text-sm text-slate-600">{{ $project->description }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.edit', [$group, $project]) }}" class="btn btn-ghost">Editar rúbrica</a>
            <a href="{{ route('export.project', [$group, $project]) }}" class="btn btn-ghost">⬇ Excel</a>
        </div>
    </div>

    @if ($book->students->isEmpty())
        <div class="card border-amber-200 bg-amber-50 p-5">
            <p class="mb-3 font-semibold text-amber-900">El grupo no tiene alumnos activos.</p>
            <a href="{{ route('students.index', $group) }}" class="btn btn-primary">Agregar alumnos</a>
        </div>
    @else
        {{-- Avance + accesos directos al modo captura por aspecto --}}
        <section class="card mb-5 p-5">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <p class="font-semibold">
                    <span data-project-graded>{{ $book->gradedCells() }}</span> de {{ $book->totalCells() }} calificaciones
                    <span class="text-slate-400">(<span data-project-progress>{{ $book->progress() }}%</span>)</span>
                </p>
                <p class="text-sm text-amber-700"><span data-project-missing>{{ $book->missingCells() }}</span> pendientes</p>
            </div>
            <x-progress :graded="$book->gradedCells()" :total="$book->totalCells()" live />

            <p class="mt-4 mb-2 text-sm font-medium text-slate-600">Capturar por aspecto</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($book->criteria as $c)
                    @php($m = $book->missingForCriterion($c))
                    <a href="{{ route('capture', [$group, $project, $c]) }}" class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 px-4 py-3 hover:border-brand-600 hover:bg-brand-50">
                        <span>
                            <span class="font-semibold">{{ $c->name }}</span>
                            <span class="text-sm text-slate-400">{{ $fmt($c->weight) }}%</span>
                        </span>
                        <span class="text-xs font-semibold {{ $m ? 'text-amber-700' : 'text-emerald-700' }}" data-criterion-missing="{{ $c->id }}">{{ $m ? "{$m} sin calificar" : '✓' }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-lg font-bold">Todas las calificaciones</h2>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" class="size-4" data-filter-pending> Solo pendientes
            </label>
        </div>

        <section class="card overflow-x-auto" data-grades-url="{{ route('grades.update', [$group, $project]) }}">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-slate-600">
                        <th class="sticky left-0 z-10 min-w-48 bg-slate-50 px-3 py-3 font-semibold">Alumno</th>
                        @foreach ($book->criteria as $c)
                            <th class="px-2 py-3 text-center font-semibold">
                                <div class="leading-tight">{{ $c->name }}</div>
                                <div class="text-xs font-normal text-slate-400">{{ $fmt($c->weight) }}%</div>
                            </th>
                        @endforeach
                        <th class="px-2 py-3 text-center font-semibold">%</th>
                        <th class="px-2 py-3 text-center font-semibold">Final</th>
                        <th class="px-3 py-3 text-center font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($book->students as $s)
                        @php($complete = $book->isComplete($s))
                        <tr data-student-row="{{ $s->id }}" data-complete="{{ $complete ? 1 : 0 }}" class="hover:bg-slate-50/60">
                            <td class="sticky left-0 z-10 bg-white px-3 py-2">
                                <span class="mr-1 inline-block w-6 text-right text-xs tabular-nums text-slate-400">{{ $s->list_number }}</span>
                                <span class="font-medium">{{ $s->name }}</span>
                            </td>
                            @foreach ($book->criteria as $c)
                                @php($score = $book->score($s->id, $c->id))
                                <td class="px-2 py-1.5 text-center">
                                    <input class="score-input" inputmode="decimal" autocomplete="off" enterkeyhint="next"
                                           value="{{ $score === null ? '' : $fmt($score) }}"
                                           data-student="{{ $s->id }}" data-criterion="{{ $c->id }}"
                                           aria-label="{{ $s->name }} · {{ $c->name }}">
                                </td>
                            @endforeach
                            <td class="px-2 text-center tabular-nums text-slate-500" data-student-percent="{{ $s->id }}">{{ $fmt($book->finalPercent($s)) }}%</td>
                            <td class="px-2 text-center text-base font-bold tabular-nums {{ $complete ? '' : 'text-slate-300' }}" data-student-final="{{ $s->id }}"
                                title="{{ $complete ? '' : 'Provisional: faltan aspectos por calificar' }}">{{ $fmt($book->finalScore($s)) }}</td>
                            <td class="px-3 text-center">
                                <span class="chip whitespace-nowrap {{ $complete ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}" data-student-status="{{ $s->id }}">
                                    {{ $complete ? 'Completo' : 'Faltan '.$book->missingFor($s)->count() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <p class="mt-2 text-xs text-slate-500">Se guarda solo al salir de cada casilla. Enter baja al siguiente alumno. Deja vacío para marcar como pendiente; 0 es una calificación.</p>
    @endif
</x-layouts.app>
