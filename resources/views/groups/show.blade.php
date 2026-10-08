@php
    $t = $overview->totals();
    $pendingStudents = $overview->studentsWithPending();
    $missingBy = $overview->students->mapWithKeys(fn ($s) => [$s->id => $overview->missingForStudent($s)]);
@endphp
<x-layouts.app title="Inicio">
    {{-- Centro de trabajo --}}
    <section class="card mb-5 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
        <div class="flex items-center gap-4">
            <div class="grid size-20 shrink-0 place-items-center rounded-2xl bg-primary text-on-primary">
                <span class="num text-3xl font-bold leading-none">{{ $group->label() }}</span>
            </div>
            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-sm font-semibold text-primary">
                    <x-icon :name="$group->shift === 'matutino' ? 'sun' : 'sunset'" class="size-4" /> Turno {{ strtolower($group->shiftLabel()) }}
                    @if ($group->school_year) <span class="text-ink-muted">· {{ $group->school_year }}</span> @endif
                </p>
                <h1 class="text-xl font-bold leading-tight sm:text-2xl">{{ $group->school_name ?? 'Mi escuela' }}</h1>
                <p class="text-sm text-ink-muted">
                    @if ($group->school_cct) CCT {{ $group->school_cct }} @endif
                    @if ($group->school_cct && $group->school_zone) · @endif
                    @if ($group->school_zone) Zona escolar {{ $group->school_zone }} @endif
                </p>
            </div>
        </div>
        <a href="{{ route('grupos.edit', $group) }}" class="btn btn-ghost sm:ml-auto"><x-icon name="settings" class="size-4" /> Datos de la escuela</a>
    </section>

    @if ($t['students'] > 0)
        {{-- Buscador: llega al alumno en un paso --}}
        <section class="relative mb-5">
            <x-search reveal />
            <div class="card absolute inset-x-0 top-full z-20 mt-2 max-h-96 overflow-y-auto shadow-xl" data-search-results hidden>
                <ul class="divide-y divide-line">
                    @foreach ($overview->students as $s)
                        <li data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}">
                            <a href="{{ route('students.show', [$group, $s]) }}" class="flex min-h-14 items-center gap-3 px-4 hover:bg-surface-2">
                                <span class="num w-7 text-right text-sm text-ink-muted">{{ $s->list_number }}</span>
                                <span class="flex-1 font-semibold">{{ $s->name }}</span>
                                @if ($missingBy[$s->id])
                                    <span class="chip chip-pending">{{ $missingBy[$s->id] }} pendientes</span>
                                @elseif ($t['projects'])
                                    <span class="chip chip-done">Al día</span>
                                @endif
                                <x-icon name="chevron-right" class="size-4 text-ink-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="p-4 text-center text-sm text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
            </div>
        </section>
    @endif

    {{-- Indicadores --}}
    <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Resumen">
        <div class="card p-4 sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-ink-muted"><x-icon name="users" class="size-4" /> Alumnos</p>
            <p class="num mt-2 text-3xl font-bold">{{ $t['students'] }}</p>
            <p class="text-xs text-ink-muted">{{ $t['projects'] }} {{ $t['projects'] === 1 ? 'proyecto' : 'proyectos' }}</p>
        </div>
        <div class="card p-4 sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-ink-muted"><x-icon name="check" class="size-4 text-done" /> Calificados al día</p>
            <p class="num mt-2 text-3xl font-bold text-done">{{ $t['complete'] }}</p>
            <p class="text-xs text-ink-muted">con todo calificado</p>
        </div>
        <div class="card p-4 sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-ink-muted"><x-icon name="alert" class="size-4 text-pending" /> Con pendientes</p>
            <p class="num mt-2 text-3xl font-bold {{ $t['pending'] ? 'text-pending' : '' }}">{{ $t['pending'] }}</p>
            <p class="text-xs text-ink-muted">{{ $t['missing'] }} calificaciones por capturar</p>
        </div>
        <div class="card p-4 sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-ink-muted"><x-icon name="chart" class="size-4" /> Avance</p>
            <p class="num mt-2 text-3xl font-bold">{{ $t['progress'] }}%</p>
            <x-progress :value="$t['progress']" class="mt-2" />
        </div>
    </section>

    @if ($t['students'] === 0)
        <section class="card flex flex-col items-center gap-3 p-8 text-center">
            <span class="grid size-14 place-items-center rounded-2xl bg-primary-soft text-primary"><x-icon name="upload" class="size-7" /></span>
            <h2 class="text-lg font-bold">Sube la lista de tu grupo</h2>
            <p class="max-w-md text-ink-muted">Un Excel con dos columnas, <strong>N.L.</strong> y <strong>Nombre</strong>, y listo. Sin capturar a mano.</p>
            <a href="{{ route('students.index', $group) }}" class="btn btn-primary">Cargar alumnos</a>
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold">Proyectos recientes</h2>
                    <a href="{{ route('projects.index', $group) }}" class="text-sm font-semibold text-primary">Ver todos</a>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($overview->recentProjects() as $project)
                        @include('projects._tile', ['project' => $project, 's' => $overview->projectSummary($project)])
                    @endforeach
                    <a href="{{ route('projects.create', $group) }}" class="tile min-h-36 items-center justify-center border-dashed text-primary">
                        <x-icon name="plus" class="size-7" />
                        <span class="font-semibold">Nuevo proyecto</span>
                    </a>
                </div>
            </section>

            <section>
                <h2 class="mb-3 text-lg font-bold">Por calificar</h2>
                @if ($pendingStudents->isEmpty())
                    <div class="card flex items-center gap-3 p-5 text-done">
                        <x-icon name="check" class="size-6" />
                        <p class="font-semibold">{{ $t['projects'] ? 'Nadie tiene calificaciones pendientes.' : 'Crea un proyecto para empezar a calificar.' }}</p>
                    </div>
                @else
                    <ul class="card divide-y divide-line">
                        @foreach ($pendingStudents->take(8) as $row)
                            <li>
                                <a href="{{ route('students.show', [$group, $row['student']]) }}" class="flex min-h-14 items-center gap-3 px-4 hover:bg-surface-2">
                                    <span class="num w-6 text-right text-sm text-ink-muted">{{ $row['student']->list_number }}</span>
                                    <span class="min-w-0 flex-1 truncate font-medium">{{ $row['student']->name }}</span>
                                    <span class="chip chip-pending">{{ $row['missing'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($pendingStudents->count() > 8)
                        <a href="{{ route('students.index', $group) }}" class="mt-2 block text-center text-sm font-semibold text-primary">y {{ $pendingStudents->count() - 8 }} más</a>
                    @endif
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
