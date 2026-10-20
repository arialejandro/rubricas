@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($active = $students->where('active', true))
@php($replaceWarning = 'Se eliminarán los '.$students->count().' alumnos actuales y todas sus calificaciones, y quedará solo la lista nueva. ¿Continuar?')
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
                <h2 class="text-lg font-bold">{{ $students->isEmpty() ? 'Carga tu lista desde Excel' : 'Subir lista desde Excel' }}</h2>
                <p class="text-sm text-ink-muted">Dos columnas: <strong>N.L.</strong> y <strong>Nombre</strong> (también acepta apellidos en columnas separadas).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('students.import', $group) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2" data-replace-guard="{{ $replaceWarning }}">
                    @csrf
                    @if ($students->isNotEmpty())
                        @include('students._replace-toggle')
                    @endif
                    <label class="btn btn-primary min-w-44">
                        <x-icon name="upload" class="size-4" />
                        <span data-file-name>Subir Excel</span>
                        <input type="file" name="file" class="sr-only" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" data-autosubmit>
                    </label>
                </form>
                <a href="{{ route('students.template', $group) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Plantilla</a>
            </div>
        </div>

        <details class="group mt-4 border-t border-line pt-4" @if (old('names')) open @endif>
            <summary class="flex min-h-11 list-none items-center gap-2 text-sm font-semibold text-primary">
                <x-icon name="chevron-right" class="size-4 transition group-open:rotate-90" /> O pega la lista (desde Excel, WhatsApp, etc.)
            </summary>
            <form method="POST" action="{{ route('students.store', $group) }}" class="mt-3 space-y-3" data-replace-guard="{{ $replaceWarning }}">
                @csrf
                <textarea class="input min-h-40 font-mono text-sm" name="names" placeholder="1&#9;López Pérez Ana&#10;2&#9;Martínez Ruiz Bruno" aria-label="Lista de alumnos">{{ old('names') }}</textarea>
                <p class="hint">Un alumno por renglón. Si empieza con número, se toma como N.L.</p>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($students->isNotEmpty())
                        @include('students._replace-toggle')
                    @endif
                    <button class="btn btn-primary">Cargar lista</button>
                </div>
            </form>
        </details>
    </section>

    @if ($students->isNotEmpty())
        <x-search class="mb-3" pending-toggle="Con pendientes" />

        <ul class="card divide-y divide-line">
            @foreach ($students as $s)
                @php($miss = $missing[$s->id] ?? 0)
                <li class="flex items-center" data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}" data-done="{{ $s->active && $miss ? 0 : 1 }}">
                    <a href="{{ route('students.show', [$group, $s]) }}" @class(['flex min-h-16 min-w-0 flex-1 items-center gap-3 px-4 hover:bg-surface-2', 'opacity-55' => ! $s->active])>
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2 text-sm font-bold tabular-nums">{{ $s->list_number ?? '–' }}</span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block font-semibold', 'line-through' => ! $s->active])>{{ $s->name }}</span>
                            @if (($averages[$s->id] ?? null) !== null)
                                <span class="text-xs text-ink-muted">Promedio <span class="font-semibold tabular-nums text-ink">{{ $fmt($averages[$s->id]) }}</span></span>
                            @endif
                        </span>
                        @if (! $s->active)
                            <span class="chip chip-muted">Baja</span>
                        @elseif ($miss)
                            <span class="chip chip-pending">{{ $miss }} pendientes</span>
                        @endif
                    </a>
                    <form method="POST" action="{{ route('students.destroy', [$group, $s]) }}" class="pr-2"
                          data-confirm="¿Eliminar a {{ $s->name }}? Se borran también sus calificaciones.">
                        @csrf @method('DELETE')
                        <input type="hidden" name="confirm" value="1">
                        <button class="btn btn-ghost btn-icon border-transparent text-ink-muted hover:text-danger" aria-label="Eliminar a {{ $s->name }}" title="Eliminar">
                            <x-icon name="trash" />
                        </button>
                    </form>
                </li>
            @endforeach
            <li class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</li>
        </ul>

        <details class="card group mt-6 border-danger/30">
            <summary class="flex min-h-14 list-none items-center gap-2 px-5 font-semibold text-danger">
                <x-icon name="trash" class="size-4" /> Vaciar la lista del grupo
                <x-icon name="chevron-right" class="ml-auto size-4 transition group-open:rotate-90" />
            </summary>
            <form method="POST" action="{{ route('students.destroyAll', $group) }}" class="space-y-3 border-t border-line p-5">
                @csrf @method('DELETE')
                <p class="text-sm text-ink-muted">Elimina a los {{ $students->count() }} alumnos y todas sus calificaciones. Los proyectos, aspectos y la configuración del grupo se conservan.</p>
                <label class="label" for="confirm_name">Escribe <strong>{{ $group->label() }}</strong> para confirmar</label>
                <input class="input" id="confirm_name" name="confirm_name" autocomplete="off">
                <button class="btn btn-danger">Vaciar lista</button>
            </form>
        </details>
    @endif
</x-layouts.app>
