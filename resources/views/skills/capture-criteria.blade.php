{{-- Captura de una aplicación de una habilidad "por criterios": alumnos × criterios (nivel 6–10). --}}
@use('App\Support\Level')
@php
    $total = $book->students->count();
    $config = $skill->criteria->mapWithKeys(fn ($c) => [$c->id => ['descriptors' => $c->descriptors(), 'labels' => $skill->labels()]]);
@endphp
<x-layouts.app :title="$skill->name" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ route('skills.show', [$group, $skill]) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Avance de {{ $skill->name }}</a>
    </x-slot:breadcrumbs>

    @if (count($tabs) > 1)
        <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Aplicaciones">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}" @class(['flex min-h-11 shrink-0 items-center rounded-xl border px-3.5 text-sm font-semibold',
                    'border-primary bg-primary text-on-primary' => $tab['active'], 'border-line bg-surface hover:border-primary' => ! $tab['active']])>{{ $tab['label'] }}</a>
            @endforeach
        </nav>
    @endif

    <div class="mb-4">
        <x-campo-chip :campo="$skill->campo" class="mb-1" />
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $skill->name }}</h1>
        <p class="text-ink-muted">{{ $application->title($number) }} · por criterios · Trimestre {{ $application->term }}</p>
    </div>

    @include('skills._application-settings')

    @if ($skill->criteria->isEmpty())
        <div class="card p-8 text-center">
            <p class="mb-3 font-semibold">Esta habilidad aún no tiene criterios.</p>
            <a href="{{ route('campos.edit', [$group, $skill->campo]) }}" class="btn btn-primary">Agregar criterios</a>
        </div>
    @else
        {{-- Criterios con su avance --}}
        <div class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($skill->criteria as $i => $c)
                @php($m = $book->appStudentsMissing($skill, $application->id, $c->id))
                <div class="card p-3">
                    <p class="mb-2 line-clamp-2 font-semibold">{{ $i + 1 }}. {{ $c->description }}</p>
                    <div class="mb-1 flex justify-between text-xs">
                        <span class="lvl-text font-semibold" data-paint="missing:appcrit:{{ $application->id }}:{{ $c->id }}" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                        <span class="tabular-nums text-ink-muted" data-paint="count:appcrit:{{ $application->id }}:{{ $c->id }}">{{ $total - $m }}/{{ $total }}</span>
                    </div>
                    <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" bar="appcrit:{{ $application->id }}:{{ $c->id }}" />
                </div>
            @endforeach
        </div>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">Calificar</h2>
            <x-level-legend />
        </div>
        <x-search class="mb-3" />

        <section class="card overflow-x-auto" data-score-url="{{ route('score.update', $group) }}" data-advance="column"
                 data-descriptors="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) }}">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-line text-ink-muted">
                        <th class="sticky left-0 z-10 min-w-52 bg-surface px-4 py-3 text-left font-semibold">Alumno</th>
                        @foreach ($skill->criteria as $i => $c)
                            <th class="w-24 px-2 py-3 text-center text-xs font-semibold" title="{{ $c->description }}"><span class="block max-w-28 truncate">{{ $i + 1 }}. {{ $c->description }}</span></th>
                        @endforeach
                        <th class="w-40 px-3 py-3 text-center font-semibold text-ink">Calificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($book->students as $s)
                        @php($as = $book->appScore($skill, $application->id, $s->id))
                        <tr data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}">
                            <td class="sticky left-0 z-10 bg-surface px-4 py-2">
                                <span class="mr-1 inline-block w-6 text-right text-xs tabular-nums text-ink-muted">{{ $s->list_number }}</span>
                                <span class="font-medium">{{ $s->name }}</span>
                            </td>
                            @foreach ($skill->criteria as $c)
                                <td class="px-2 py-1.5 text-center">
                                    <x-score kind="skillcrit" :id="$c->id" :student="$s" :unit="$c->description" :value="$book->appCriterionScore($s->id, $application->id, $c->id)"
                                             mode="levels" data-app="{{ $application->id }}" class="w-16" />
                                </td>
                            @endforeach
                            <td class="px-3 text-center">
                                <span class="lvl-chip w-36 justify-center tabular-nums" data-paint="app:{{ $application->id }}:{{ $s->id }}"
                                      @if ($as !== null) data-level="{{ $book->appMissing($skill, $application->id, $s->id) ? 'provisional' : Level::of($as) }}" @endif>
                                    {{ $as === null ? '—' : $as.' · '.$skill->levelLabel(Level::of($as)) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
        </section>
        <p class="mt-2 text-xs text-ink-muted">La calificación es el promedio de los criterios, redondeado al nivel: En proceso queda en 7 u 8 según la mitad del rango en la que caiga.</p>
    @endif

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
