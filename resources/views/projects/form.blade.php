@php
    // Tras un error de validación se reconstruyen los renglones con lo que la maestra escribió.
    $rows = old('criteria', $project->criteria->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'weight' => rtrim(rtrim(number_format($c->weight, 2, '.', ''), '0'), '.'),
        'graded' => $c->grades_count ?? 0,
    ])->all());
    if (empty($rows)) {
        $rows = [['id' => null, 'name' => '', 'weight' => ''], ['id' => null, 'name' => '', 'weight' => '']];
    }
@endphp

<x-layouts.app :title="$project->exists ? 'Editar proyecto' : 'Nuevo proyecto'">
    <x-slot:breadcrumbs>
        <a href="{{ $project->exists ? route('projects.show', [$group, $project]) : route('projects.index', $group) }}" class="flex items-center gap-1 hover:text-primary">
            <x-icon name="chevron-left" class="size-4" /> {{ $project->exists ? $project->name : 'Proyectos' }}
        </a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }}</h1>

        <form method="POST" action="{{ $project->exists ? route('projects.update', [$group, $project]) : route('projects.store', $group) }}" class="space-y-6">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <section class="card space-y-4 p-5 sm:p-6">
                <div>
                    <label class="label" for="name">Nombre del proyecto</label>
                    <input class="input text-lg" id="name" name="name" value="{{ old('name', $project->name) }}" placeholder="Maqueta del sistema solar" required @unless ($project->exists) autofocus @endunless>
                </div>
                <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <div>
                        <label class="label" for="description">Descripción <span class="font-normal text-ink-muted">(opcional)</span></label>
                        <input class="input" id="description" name="description" value="{{ old('description', $project->description) }}">
                    </div>
                    <div>
                        <label class="label" for="due_date">Fecha <span class="font-normal text-ink-muted">(opcional)</span></label>
                        <input class="input" id="due_date" type="date" name="due_date" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </section>

            <section class="card space-y-4 p-5 sm:p-6" data-criteria-editor>
                <div>
                    <h2 class="text-lg font-bold">Rúbrica</h2>
                    <p class="text-sm text-ink-muted">Cada aspecto se califica del 0 al 10 y vale un porcentaje del proyecto. Un 8 en un aspecto del 20% aporta 16%.</p>
                </div>

                <div class="space-y-2" data-criteria-list>
                    @foreach ($rows as $i => $row)
                        @include('projects._criterion-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>

                <template>
                    @include('projects._criterion-row', ['i' => '__INDEX__', 'row' => ['id' => null, 'name' => '', 'weight' => '']])
                </template>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-ghost" data-add-criterion><x-icon name="plus" class="size-4" /> Agregar aspecto</button>
                    <button type="button" class="btn btn-ghost" data-split-even>Repartir igual</button>
                </div>

                <div class="rounded-xl bg-surface-2 p-3">
                    <div class="mb-1.5 flex items-center justify-between text-sm">
                        <span class="font-medium">Total (debe ser 100%)</span>
                        <span class="num text-lg font-bold" data-weight-total>0%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full transition-[width]" data-weight-bar></div></div>
                </div>

                @if ($project->exists)
                    <label class="flex items-start gap-3 rounded-xl border border-line p-3 text-sm text-ink-muted">
                        <input type="checkbox" name="confirm_remove" value="1" class="mt-0.5 size-5">
                        Si quito un aspecto que ya tiene calificaciones, confirmo que se borren esas calificaciones.
                    </label>
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ $project->exists ? route('projects.show', [$group, $project]) : route('projects.index', $group) }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary min-w-44">Guardar proyecto</button>
            </div>
        </form>

        @if ($project->exists)
            <form method="POST" action="{{ route('projects.destroy', [$group, $project]) }}" class="card space-y-3 border-danger/30 p-5 sm:p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-danger">Eliminar proyecto</h2>
                <label class="flex items-start gap-3 text-sm text-ink-muted">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-5">
                    Entiendo que se borran todas las calificaciones de este proyecto.
                </label>
                <button class="btn btn-danger">Eliminar proyecto</button>
            </form>
        @endif
    </div>
</x-layouts.app>
