@use('App\Support\Campos')
@use('App\Support\Level')
@use('App\Support\TermBook')
@php
    $aspects = $book->aspectsFor($campo);
    $subjects = $book->subjectsFor($campo);
    $skills = $book->skillsFor($campo);
    $products = $book->productsFor($campo);
    $p = $book->campoProgress($campo);
    $total = $book->students->count();
@endphp
<x-layouts.app :title="Campos::short($campo)">
    <x-slot:breadcrumbs>
        <a href="{{ route('campos.index', $group) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Campos</a>
    </x-slot:breadcrumbs>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3" style="--c: {{ Campos::color($campo) }}">
        <div>
            <x-campo-chip :campo="$campo" class="mb-1" />
            <h1 class="text-2xl font-bold sm:text-3xl">{{ Campos::name($campo) }}</h1>
            <p class="text-ink-muted">Trimestre {{ $book->term }} · <span data-paint="campo-progress:{{ $campo }}">{{ $p['progress'] }}%</span> capturado</p>
        </div>
        <a href="{{ route('campos.edit', [$group, $campo]) }}" class="btn btn-ghost"><x-icon name="settings" class="size-4" /> Aspectos y materias</a>
    </div>

    @if (! $book->isConfigured($campo))
        <div class="card flex flex-col items-center gap-3 p-8 text-center">
            <h2 class="text-lg font-bold">Define cómo se evalúa este campo en el trimestre</h2>
            <p class="max-w-md text-ink-muted">Entre 3 y 6 aspectos con su porcentaje (proyectos, examen, tareas…).</p>
            <a href="{{ route('campos.edit', [$group, $campo]) }}" class="btn btn-primary">Definir aspectos</a>
        </div>
    @else
        @if ($book->skillsAspectWithoutSkills($campo))
            <div class="card mb-4 flex flex-wrap items-center gap-3 border-pending/40 bg-pending-soft p-4 text-pending">
                <x-icon name="alert" />
                <p class="flex-1 font-semibold">El aspecto de habilidades prioritarias no tiene habilidades definidas.</p>
                <a href="{{ route('campos.edit', [$group, $campo]) }}" class="btn btn-primary">Definir habilidades</a>
            </div>
        @endif
        @if ($book->projectsAspectWithoutProducts($campo))
            <div class="card mb-4 flex flex-wrap items-center gap-3 border-pending/40 bg-pending-soft p-4 text-pending">
                <x-icon name="alert" />
                <p class="flex-1 font-semibold">El aspecto de proyectos no tiene todavía ningún producto evaluado en este campo.</p>
                <a href="{{ route('projects.create', [$group, 'campo' => $campo]) }}" class="btn btn-primary">Nuevo proyecto</a>
            </div>
        @endif

        {{-- Aspectos del trimestre: tap → capturar (los calculados llevan a lo que los alimenta) --}}
        <h2 class="mb-3 text-lg font-bold">Aspectos del trimestre</h2>
        <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($aspects as $a)
                @if ($a->isFromProjects())
                    <div class="tile min-h-32 cursor-default hover:border-line active:scale-100">
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded-lg bg-surface-2 px-2 py-1 text-sm font-bold tabular-nums">{{ TermBook::fmt($a->weight) }}%</span>
                            <x-icon name="folder" class="size-5 text-ink-muted" />
                        </div>
                        <h3 class="text-lg font-bold leading-snug">{{ $a->name }}</h3>
                        <ul class="mt-auto space-y-1 text-xs">
                            @forelse ($products as $prod)
                                <li><a href="{{ route('projects.show', [$group, $prod->project]) }}" class="font-medium text-primary hover:underline">{{ $prod->name }}</a>
                                    <span class="text-ink-muted">· {{ $prod->project->name }}</span></li>
                            @empty
                                <li class="text-pending">Sin productos en este campo</li>
                            @endforelse
                        </ul>
                    </div>
                @elseif ($a->isFromSkills())
                    <a href="{{ $skills->isEmpty() ? route('campos.edit', [$group, $campo]) : route('capture.skill', [$group, $skills->first()]) }}" class="tile min-h-32 border-primary/50">
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded-lg bg-surface-2 px-2 py-1 text-sm font-bold tabular-nums">{{ TermBook::fmt($a->weight) }}%</span>
                            <x-icon name="chevron-right" class="size-5 text-ink-muted" />
                        </div>
                        <h3 class="text-lg font-bold leading-snug">{{ $a->name }}</h3>
                        <p class="mt-auto text-xs font-semibold {{ $skills->isEmpty() ? 'text-pending' : 'text-primary' }}">
                            {{ $skills->isEmpty() ? 'Definir habilidades' : 'Calificar '.$skills->count().' habilidades' }}
                        </p>
                    </a>
                @else
                    @php($m = $book->aspectMissing($a))
                    <a href="{{ route('capture.aspect', [$group, $a]) }}" class="tile min-h-32">
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded-lg bg-surface-2 px-2 py-1 text-sm font-bold tabular-nums">{{ TermBook::fmt($a->weight) }}%</span>
                            <x-icon name="chevron-right" class="size-5 text-ink-muted" />
                        </div>
                        <h3 class="text-lg font-bold leading-snug">{{ $a->name }}</h3>
                        <div class="mt-auto">
                            <div class="mb-1 flex justify-between text-xs">
                                <span class="lvl-text font-semibold" data-paint="missing:aspect:{{ $a->id }}" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                                <span class="tabular-nums text-ink-muted" data-paint="count:aspect:{{ $a->id }}">{{ $total - $m }}/{{ $total }}</span>
                            </div>
                            <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" bar="aspect:{{ $a->id }}" />
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        @if ($skills->isNotEmpty())
            <section id="habilidades" class="mb-6 scroll-mt-24">
                <h2 class="text-lg font-bold">Habilidades prioritarias</h2>
                <p class="mb-3 text-sm text-ink-muted">Toca una habilidad para calificar a todo el grupo por nivel. Su promedio es el aspecto "{{ $aspects->first(fn ($a) => $a->isFromSkills())->name }}".</p>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($skills as $skill)
                @php($m = $book->skillMissing($skill))
                <a href="{{ route('capture.skill', [$group, $skill]) }}" class="tile min-h-32">
                    <div class="flex items-start justify-between gap-2">
                        <span class="rounded-lg bg-primary-soft px-2 py-1 text-xs font-bold text-primary">Habilidad</span>
                        <x-icon name="chevron-right" class="size-5 text-ink-muted" />
                    </div>
                    <h3 class="text-lg font-bold leading-snug">{{ $skill->name }}</h3>
                    <div class="mt-auto">
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="lvl-text font-semibold" data-paint="missing:skill:{{ $skill->id }}" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                            <span class="tabular-nums text-ink-muted" data-paint="count:skill:{{ $skill->id }}">{{ $total - $m }}/{{ $total }}</span>
                        </div>
                        <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" bar="skill:{{ $skill->id }}" />
                    </div>
                </a>
            @endforeach
                </div>
            </section>
        @endif

        @if ($subjects->isNotEmpty())
            <h2 class="text-lg font-bold">Materias adicionales</h2>
            <p class="mb-3 text-sm text-ink-muted">Promedian en partes iguales con el campo.</p>
            <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($subjects as $sub)
                @php($m = $book->subjectMissing($sub))
                <a href="{{ route('capture.subject', [$group, $sub]) }}" class="tile min-h-32 border-dashed">
                    <div class="flex items-start justify-between gap-2">
                        <span class="rounded-lg bg-mint-soft px-2 py-1 text-xs font-bold text-mint-strong">Materia</span>
                        <x-icon name="chevron-right" class="size-5 text-ink-muted" />
                    </div>
                    <h3 class="text-lg font-bold leading-snug">{{ $sub->name }}</h3>
                    <div class="mt-auto">
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="lvl-text font-semibold" data-paint="missing:subject:{{ $sub->id }}" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                            <span class="tabular-nums text-ink-muted" data-paint="count:subject:{{ $sub->id }}">{{ $total - $m }}/{{ $total }}</span>
                        </div>
                        <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" bar="subject:{{ $sub->id }}" />
                    </div>
                </a>
            @endforeach
            </div>
        @endif

        @if (abs($book->totalWeight($campo) - 100) >= 0.01)
            <p class="mb-4 rounded-xl bg-danger-soft p-3 text-sm font-semibold text-danger">Los aspectos suman {{ TermBook::fmt($book->totalWeight($campo)) }}%; deben sumar 100%.</p>
        @endif

        {{-- Matriz del campo --}}
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">Calificaciones del campo</h2>
            <x-level-legend />
        </div>
        <x-search class="mb-3" pending-toggle="Solo pendientes" />

        <section class="card overflow-x-auto" data-score-url="{{ route('score.update', $group) }}" data-advance="column"
                 data-descriptors="{{ json_encode($skills->mapWithKeys(fn ($k) => [$k->id => $k->keypadConfig()]), JSON_UNESCAPED_UNICODE) }}">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-ink-muted">
                        <th class="sticky left-0 z-10 min-w-52 bg-surface px-4 py-3 font-semibold">Alumno</th>
                        @foreach ($aspects as $a)
                            <th class="px-2 py-3 text-center font-semibold">
                                <span class="block max-w-28 truncate text-ink" title="{{ $a->name }}">{{ $a->name }}</span>
                                <span class="text-xs font-normal tabular-nums">{{ TermBook::fmt($a->weight) }}%</span>
                            </th>
                        @endforeach
                        @foreach ($skills as $skill)
                            <th class="bg-primary-soft/40 px-2 py-3 text-center font-semibold"><span class="block max-w-28 truncate text-ink" title="{{ $skill->name }}">{{ $skill->name }}</span><span class="text-xs font-normal">habilidad</span></th>
                        @endforeach
                        @if ($subjects->isNotEmpty())
                            <th class="px-2 py-3 text-center font-semibold text-ink">Base</th>
                            @foreach ($subjects as $sub)
                                <th class="px-2 py-3 text-center font-semibold"><span class="block max-w-28 truncate text-ink">{{ $sub->name }}</span><span class="text-xs font-normal">materia</span></th>
                            @endforeach
                        @endif
                        <th class="px-2 py-3 text-center font-semibold text-ink">Final</th>
                        <th class="px-4 py-3 text-center font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($book->students as $s)
                        {{-- Asignaciones en línea a propósito: un bloque php largo después de uno en línea rompe el compilador de Blade. --}}
                        @php($complete = $book->campoComplete($campo, $s))
                        @php($final = $book->campoFinal($campo, $s))
                        @php($base = $book->campoBase($campo, $s))
                        <tr data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}"
                            data-done="{{ $complete ? 1 : 0 }}" data-paint-done="row:{{ $campo }}:{{ $s->id }}">
                            <td class="sticky left-0 z-10 bg-surface px-4 py-2">
                                <span class="mr-1 inline-block w-6 text-right text-xs tabular-nums text-ink-muted">{{ $s->list_number }}</span>
                                <a href="{{ route('students.show', [$group, $s]) }}" class="font-medium hover:text-primary">{{ $s->name }}</a>
                            </td>
                            @foreach ($aspects as $a)
                                <td class="px-2 py-1.5 text-center">
                                    @if ($a->isFromProjects())
                                        @php($pv = $book->projectsValue($campo, $s))
                                        <span class="lvl-text inline-grid min-h-12 min-w-14 place-items-center font-bold tabular-nums" data-paint="projects:{{ $campo }}:{{ $s->id }}" @if ($pv !== null) data-level="{{ Level::of($pv) }}" @endif title="Promedio de productos de proyectos">{{ TermBook::fmt($pv) }}</span>
                                    @elseif ($a->isFromSkills())
                                        @php($sv = $book->skillsValue($campo, $s))
                                        <span class="lvl-text inline-grid min-h-12 min-w-14 place-items-center font-bold tabular-nums" data-paint="skills:{{ $campo }}:{{ $s->id }}" @if ($sv !== null) data-level="{{ Level::of($sv) }}" @endif title="Promedio de habilidades prioritarias">{{ TermBook::fmt($sv) }}</span>
                                    @else
                                        <x-score kind="aspect" :id="$a->id" :student="$s" :unit="$a->name" :value="$book->aspectScore($s->id, $a->id)" />
                                    @endif
                                </td>
                            @endforeach
                            @foreach ($skills as $skill)
                                <td class="bg-primary-soft/40 px-2 py-1.5 text-center"><x-score kind="skill" :id="$skill->id" :term="$book->term" :student="$s" :unit="$skill->name" :value="$book->skillScore($s->id, $skill->id)"
                                    :mode="$skill->isMeasured() ? 'measure' : null" :measure="$book->skillMeasure($s->id, $skill->id)" /></td>
                            @endforeach
                            @if ($subjects->isNotEmpty())
                                <td class="lvl-text px-2 text-center font-semibold tabular-nums" data-paint="base:{{ $campo }}:{{ $s->id }}" @if ($base !== null) data-level="{{ Level::of($base) }}" @endif>{{ TermBook::fmt($base) }}</td>
                                @foreach ($subjects as $sub)
                                    <td class="px-2 py-1.5 text-center"><x-score kind="subject" :id="$sub->id" :term="$book->term" :student="$s" :unit="$sub->name" :value="$book->subjectScore($s->id, $sub->id)" /></td>
                                @endforeach
                            @endif
                            <td class="lvl-text px-2 text-center text-lg font-bold tabular-nums" data-paint="final:{{ $campo }}:{{ $s->id }}" data-level="{{ $complete ? Level::of($final) : 'provisional' }}">{{ TermBook::fmt($final) }}</td>
                            <td class="px-4 text-center">
                                <span class="lvl-chip" data-paint="status:{{ $campo }}:{{ $s->id }}" data-level="{{ $complete ? 'done' : 'pending' }}">{{ $complete ? 'Completo' : 'Faltan '.$book->campoMissing($campo, $s) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
        </section>
        <p class="mt-2 text-xs text-ink-muted">Base = aspectos con su porcentaje. Final = promedio de la base y las materias del campo. Una calificación en gris es provisional: aún hay pendientes.</p>
    @endif

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
