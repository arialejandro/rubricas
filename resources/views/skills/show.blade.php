@use('App\Support\Campos')
@use('App\Support\Level')
@use('App\Support\TermBook')
@php
    $missing = $book->skillMissing($skill);
    $total = $book->students->count();
@endphp
<x-layouts.app :title="$skill->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('campos.show', [$group, $skill->campo]) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> {{ Campos::name($skill->campo) }}</a>
    </x-slot:breadcrumbs>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <x-campo-chip :campo="$skill->campo" class="mb-1" />
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $skill->name }}</h1>
            <p class="text-ink-muted">
                Habilidad prioritaria · Trimestre {{ $book->term }} ·
                {{ \App\Models\Skill::GRADINGS[$skill->grading] ?? '' }}{{ $skill->isMeasured() ? ' ('.$skill->measure_unit.')' : '' }}
            </p>
        </div>
        <a href="{{ route('export.skill', [$group, $skill]) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Excel</a>
    </div>

    @if ($skill->isMeasured())
        <x-skill-ranges :skill="$skill" :title="false" class="mb-4" />
    @elseif ($skill->isByCriteria() && $skill->criteria->isEmpty())
        <div class="card mb-4 flex flex-wrap items-center gap-3 border-pending/40 bg-pending-soft p-4 text-pending">
            <x-icon name="alert" />
            <p class="flex-1 font-semibold">Esta habilidad se califica por criterios, pero aún no tiene criterios.</p>
            <a href="{{ route('campos.edit', [$group, $skill->campo]) }}" class="btn btn-primary">Agregar criterios</a>
        </div>
    @endif

    {{-- Aplicaciones del trimestre --}}
    <section class="card mb-5 p-4 sm:p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">Aplicaciones</h2>
            <p class="text-sm text-ink-muted">La calificación de la habilidad es el <strong>promedio</strong> de sus aplicaciones.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($apps as $i => $app)
                @php($m = $book->appStudentsMissing($skill, $app->id))
                <a href="{{ route('skills.capture', [$group, $skill, $app]) }}" class="tile min-h-0 w-full p-3 sm:w-56">
                    <span class="font-bold">{{ $app->title($i + 1) }}</span>
                    <span class="lvl-text text-xs font-semibold" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completa' }}</span>
                </a>
            @endforeach
            <form method="POST" action="{{ route('skills.applications.store', [$group, $skill]) }}" class="tile min-h-0 w-full gap-2 border-dashed p-3 sm:w-72">
                @csrf
                <span class="font-bold text-primary">+ Nueva aplicación</span>
                <div class="flex gap-2">
                    <input type="date" name="applied_on" value="{{ now()->format('Y-m-d') }}" class="input min-h-10 flex-1 py-1.5 text-sm" aria-label="Fecha de la aplicación">
                    <button class="btn btn-primary min-h-10 px-3">Calificar</button>
                </div>
            </form>
        </div>
    </section>

    {{-- Avance: alumno × aplicación --}}
    @if ($apps->isNotEmpty())
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">Avance del grupo</h2>
            <x-level-legend />
        </div>
        <x-search class="mb-3" />
        <section class="card overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-line text-ink-muted">
                        <th class="sticky left-0 z-10 min-w-52 bg-surface px-4 py-3 text-left font-semibold">Alumno</th>
                        @foreach ($apps as $i => $app)
                            <th class="px-2 py-3 text-center font-semibold">
                                <a href="{{ route('skills.capture', [$group, $skill, $app]) }}" class="text-primary hover:underline">{{ $app->title($i + 1) }}</a>
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center font-semibold text-ink">Promedio</th>
                        <th class="px-3 py-3 text-center font-semibold">Avance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($book->students as $s)
                        @php($series = $apps->map(fn ($a) => $book->appScore($skill, $a->id, $s->id))->filter(fn ($v) => $v !== null)->values())
                        @php($avg = $book->skillScore($s->id, $skill->id))
                        <tr data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}">
                            <td class="sticky left-0 z-10 bg-surface px-4 py-2">
                                <span class="mr-1 inline-block w-6 text-right text-xs tabular-nums text-ink-muted">{{ $s->list_number }}</span>
                                <span class="font-medium">{{ $s->name }}</span>
                            </td>
                            @foreach ($apps as $app)
                                @php($v = $book->appScore($skill, $app->id, $s->id))
                                @php($mm = $skill->isMeasured() ? $book->appMeasure($s->id, $app->id) : null)
                                <td class="px-2 py-1.5 text-center">
                                    @if ($v === null)
                                        <span class="text-ink-muted">—</span>
                                    @else
                                        <span class="lvl-chip w-16 justify-center tabular-nums" data-level="{{ $book->appMissing($skill, $app->id, $s->id) ? 'provisional' : Level::of($v) }}" title="{{ $skill->levelLabel(Level::of($v)) }}">
                                            {{ $mm !== null ? TermBook::fmt($mm).' · ' : '' }}{{ $v }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="lvl-text px-3 text-center text-lg font-bold tabular-nums" @if ($avg !== null) data-level="{{ Level::of($avg) }}" @endif>{{ TermBook::fmt($avg) }}</td>
                            <td class="px-3 text-center">
                                @if ($series->count() >= 2)
                                    @php($delta = $series->last() - $series->first())
                                    <span @class(['inline-flex items-center gap-1 text-sm font-bold', 'text-done' => $delta > 0, 'text-danger' => $delta < 0, 'text-ink-muted' => $delta == 0])
                                          title="De {{ $series->first() }} a {{ $series->last() }}">
                                        {{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '=') }} {{ $delta > 0 ? '+' : '' }}{{ $delta }}
                                    </span>
                                @else
                                    <span class="text-ink-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
        </section>
        <p class="mt-2 text-xs text-ink-muted">Avance = diferencia entre la primera y la última aplicación calificada.{{ $skill->isMeasured() ? ' Cada celda muestra la medición y su calificación.' : '' }}</p>
    @else
        <div class="card p-8 text-center text-ink-muted">Aún no hay aplicaciones este trimestre. Crea la primera con su fecha y empieza a calificar.</div>
    @endif
</x-layouts.app>
