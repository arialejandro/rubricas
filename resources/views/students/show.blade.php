@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
<x-layouts.app :title="$student->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('students.index', $group) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Alumnos</a>
    </x-slot:breadcrumbs>

    <section class="mb-6 flex flex-wrap items-center gap-4">
        <span class="num grid size-16 shrink-0 place-items-center rounded-2xl bg-primary text-2xl font-bold text-on-primary">{{ $student->list_number ?? '–' }}</span>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $student->name }}</h1>
            <p class="text-ink-muted">{{ $group->label() }} · {{ $group->shiftLabel() }}
                @unless ($student->active) · <span class="chip chip-muted">Dado de baja</span> @endunless
            </p>
        </div>
    </section>

    @if ($rows->isEmpty())
        <div class="card p-8 text-center text-ink-muted">Aún no hay proyectos en el grupo.</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($rows as $row)
            @php($p = $row['project'])
            @php($key = $p->id.':'.$student->id)
            <section class="card p-4 sm:p-5" @if ($student->active) data-grades-url="{{ route('grades.update', [$group, $p]) }}" @endif>
                <div class="mb-3 flex items-start justify-between gap-3">
                    <a href="{{ route('projects.show', [$group, $p]) }}" class="min-w-0 font-bold hover:text-primary">{{ $p->name }}</a>
                    <div class="text-right">
                        <span class="num block text-2xl font-bold leading-none {{ $row['missing'] ? 'opacity-40' : '' }}" data-final="{{ $key }}">{{ $fmt($row['percent'] / 10) }}</span>
                        <span class="chip mt-1 {{ $row['missing'] ? 'chip-pending' : 'chip-done' }}" data-status="{{ $key }}">{{ $row['missing'] ? 'Faltan '.$row['missing'] : 'Completo' }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($p->criteria as $c)
                        <div class="flex items-center justify-between gap-2 rounded-xl bg-surface-2 p-2 pl-3">
                            <span class="min-w-0 text-sm leading-tight">
                                <span class="block truncate font-medium" title="{{ $c->name }}">{{ $c->name }}</span>
                                <span class="num text-xs text-ink-muted">{{ $fmt($c->weight) }}%</span>
                            </span>
                            @if ($student->active)
                                <x-score :student="$student" :criterion="$c" :value="$scores[$c->id] ?? null" />
                            @else
                                <span class="num font-bold">{{ isset($scores[$c->id]) ? $fmt($scores[$c->id]) : '—' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

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
                    <input class="input num" id="list_number" name="list_number" type="number" inputmode="numeric" min="1" value="{{ $student->list_number }}">
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
            @unless ($student->grades()->exists())
                <form method="POST" action="{{ route('students.destroy', [$group, $student]) }}" data-confirm="¿Eliminar a {{ $student->name }}?">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger">Eliminar alumno</button>
                </form>
            @endunless
        </div>
    </details>

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
