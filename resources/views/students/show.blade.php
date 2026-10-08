@use('App\Support\Campos')
@use('App\Support\Level')
@use('App\Support\TermBook')
@php
    $active = $student->active && $book->students->contains('id', $student->id);
    $s = $student;
    $avg = $active ? $book->generalAverage($s) : null;
@endphp
<x-layouts.app :title="$student->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('students.index', $group) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Alumnos</a>
    </x-slot:breadcrumbs>

    <section class="mb-6 flex flex-wrap items-center gap-4">
        <span class="grid size-16 shrink-0 place-items-center rounded-2xl bg-primary text-2xl font-bold tabular-nums text-on-primary">{{ $student->list_number ?? '–' }}</span>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $student->name }}</h1>
            <p class="text-ink-muted">{{ $group->label() }} · {{ $group->shiftLabel() }} · Trimestre {{ $book->term }}
                @unless ($student->active) · <span class="chip chip-muted">Dado de baja</span> @endunless
            </p>
        </div>
        @if ($active)
            <div class="text-right">
                <span class="lvl-text block text-3xl font-bold tabular-nums" data-paint="general:{{ $s->id }}" @if ($avg !== null) data-level="{{ Level::of($avg) }}" @endif>{{ TermBook::fmt($avg) }}</span>
                <span class="text-xs text-ink-muted">promedio del trimestre</span>
            </div>
        @endif
    </section>

    @if ($active)
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach (Campos::keys() as $campo)
                @php
                    $final = $book->campoFinal($campo, $s);
                    $complete = $book->campoComplete($campo, $s);
                @endphp
                <section class="card overflow-hidden" style="--c: {{ Campos::color($campo) }}" data-score-url="{{ route('score.update', $group) }}">
                    <div class="flex items-start justify-between gap-3 border-b border-line p-4">
                        <div>
                            <x-campo-chip :campo="$campo" />
                            <h2 class="mt-1 font-bold">{{ Campos::name($campo) }}</h2>
                        </div>
                        <div class="text-right">
                            <span class="lvl-text block text-2xl font-bold tabular-nums leading-none" data-paint="final:{{ $campo }}:{{ $s->id }}" data-level="{{ $complete ? Level::of($final) : 'provisional' }}">{{ TermBook::fmt($final) }}</span>
                            @if ($book->isConfigured($campo))
                                <span class="lvl-chip mt-1" data-paint="status:{{ $campo }}:{{ $s->id }}" data-level="{{ $complete ? 'done' : 'pending' }}">{{ $complete ? 'Completo' : 'Faltan '.$book->campoMissing($campo, $s) }}</span>
                            @endif
                        </div>
                    </div>
                    @if (! $book->isConfigured($campo))
                        <p class="p-4 text-sm text-ink-muted">Sin aspectos definidos en este trimestre. <a href="{{ route('campos.edit', [$group, $campo]) }}" class="font-semibold text-primary">Definir</a></p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($book->aspectsFor($campo) as $a)
                                <li class="flex min-h-14 items-center gap-3 px-4 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium">{{ $a->name }} <span class="text-xs tabular-nums text-ink-muted">{{ TermBook::fmt($a->weight) }}%</span></span>
                                        @if ($a->isFromProjects())
                                            <span class="text-xs text-ink-muted">
                                                @forelse ($book->productsFor($campo) as $prod)
                                                    {{ $prod->name }}: <span class="lvl-text font-semibold" data-paint="product:{{ $prod->id }}:{{ $s->id }}">{{ TermBook::fmt($book->productScore($prod, $s)) }}</span>@if (! $loop->last) · @endif
                                                @empty
                                                    Sin productos en este campo
                                                @endforelse
                                            </span>
                                        @endif
                                    </span>
                                    @if ($a->isFromProjects())
                                        @php($pv = $book->projectsValue($campo, $s))
                                        <span class="lvl-text inline-grid min-h-12 min-w-14 place-items-center text-lg font-bold tabular-nums" data-paint="projects:{{ $campo }}:{{ $s->id }}" @if ($pv !== null) data-level="{{ Level::of($pv) }}" @endif>{{ TermBook::fmt($pv) }}</span>
                                    @else
                                        <x-score kind="aspect" :id="$a->id" :student="$s" :unit="$a->name" :value="$book->aspectScore($s->id, $a->id)" />
                                    @endif
                                </li>
                            @endforeach
                            @foreach ($book->subjectsFor($campo) as $sub)
                                <li class="flex min-h-14 items-center gap-3 bg-surface-2/50 px-4 py-2">
                                    <span class="flex-1 font-medium">{{ $sub->name }} <span class="text-xs text-ink-muted">materia</span></span>
                                    <x-score kind="subject" :id="$sub->id" :term="$book->term" :student="$s" :unit="$sub->name" :value="$book->subjectScore($s->id, $sub->id)" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    {{-- Datos del alumno --}}
    <details class="card group mt-6">
        <summary class="flex min-h-14 list-none items-center gap-2 px-5 font-semibold">
            <x-icon name="pencil" class="size-4" /> Editar datos o dar de baja
            <x-icon name="chevron-right" class="ml-auto size-4 transition group-open:rotate-90" />
        </summary>
        <div class="space-y-4 border-t border-line p-5">
            <form method="POST" action="{{ route('students.update', [$group, $student]) }}" class="grid gap-3 sm:grid-cols-[7rem_1fr]">
                @csrf @method('PUT')
                <div>
                    <label class="label" for="list_number">N.L.</label>
                    <input class="input tabular-nums" id="list_number" name="list_number" type="number" inputmode="numeric" min="1" value="{{ $student->list_number }}">
                </div>
                <div>
                    <label class="label" for="name">Nombre</label>
                    <input class="input" id="name" name="name" value="{{ $student->name }}" required>
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <input type="hidden" name="active" value="{{ $student->active ? 1 : 0 }}">
                    <button class="btn btn-primary">Guardar</button>
                    @if ($student->active)
                        <button class="btn btn-ghost" name="active" value="0">Dar de baja</button>
                    @else
                        <button class="btn btn-ghost" name="active" value="1">Reactivar</button>
                    @endif
                </div>
            </form>
            <p class="hint">Dar de baja conserva sus calificaciones y deja de contarlo como pendiente.</p>
            @unless ($student->hasScores())
                <form method="POST" action="{{ route('students.destroy', [$group, $student]) }}" data-confirm="¿Eliminar a {{ $student->name }}?">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger">Eliminar alumno</button>
                </form>
            @endunless
        </div>
    </details>

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
