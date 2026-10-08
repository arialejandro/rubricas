@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($active = $students->where('active', true))
<x-layouts.app title="Alumnos">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Alumnos</h1>
            <p class="text-ink-muted">{{ $group->label() }} · {{ $active->count() }} activos</p>
        </div>
    </div>

    {{-- Carga de la lista: Excel primero, pegar texto como respaldo --}}
    <section @class(['card mb-6 p-5 sm:p-6', 'border-primary/40' => $students->isEmpty()])>
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="flex-1">
                <h2 class="text-lg font-bold">{{ $students->isEmpty() ? 'Carga tu lista desde Excel' : 'Actualizar lista desde Excel' }}</h2>
                <p class="text-sm text-ink-muted">Dos columnas: <strong>N.L.</strong> y <strong>Nombre</strong> (también acepta apellidos en columnas separadas). Los que ya existen no se duplican; solo se actualiza su número.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('students.import', $group) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="btn btn-primary min-w-44">
                        <x-icon name="upload" class="size-4" />
                        <span data-file-name>Subir Excel</span>
                        <input type="file" name="file" class="sr-only" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" data-autosubmit>
                    </label>
                </form>
                <a href="{{ route('students.template', $group) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Plantilla</a>
            </div>
        </div>

        <details class="group mt-4 border-t border-line pt-4">
            <summary class="flex min-h-11 list-none items-center gap-2 text-sm font-semibold text-primary">
                <x-icon name="chevron-right" class="size-4 transition group-open:rotate-90" /> O pega la lista (desde Excel, WhatsApp, etc.)
            </summary>
            <form method="POST" action="{{ route('students.store', $group) }}" class="mt-3 space-y-3">
                @csrf
                <textarea class="input min-h-40 font-mono text-sm" name="names" placeholder="1&#9;López Pérez Ana&#10;2&#9;Martínez Ruiz Bruno" aria-label="Lista de alumnos">{{ old('names') }}</textarea>
                <p class="hint">Un alumno por renglón. Si empieza con número, se toma como N.L.</p>
                <button class="btn btn-primary">Agregar</button>
            </form>
        </details>
    </section>

    @if ($students->isNotEmpty())
        <x-search class="mb-3" pending-toggle="Con pendientes" />

        <ul class="card divide-y divide-line">
            @foreach ($students as $s)
                @php($miss = $missing[$s->id] ?? 0)
                <li data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}" data-done="{{ $s->active && $miss ? 0 : 1 }}">
                    <a href="{{ route('students.show', [$group, $s]) }}" @class(['flex min-h-16 items-center gap-3 px-4 hover:bg-surface-2', 'opacity-55' => ! $s->active])>
                        <span class="num grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2 text-sm font-bold">{{ $s->list_number ?? '–' }}</span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block font-semibold', 'line-through' => ! $s->active])>{{ $s->name }}</span>
                            @if (($averages[$s->id] ?? null) !== null)
                                <span class="text-xs text-ink-muted">Promedio <span class="num font-semibold text-ink">{{ $fmt($averages[$s->id]) }}</span></span>
                            @endif
                        </span>
                        @if (! $s->active)
                            <span class="chip chip-muted">Baja</span>
                        @elseif ($miss)
                            <span class="chip chip-pending">{{ $miss }} pendientes</span>
                        @endif
                        <x-icon name="chevron-right" class="size-4 text-ink-muted" />
                    </a>
                </li>
            @endforeach
            <li class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</li>
        </ul>
    @endif
</x-layouts.app>
