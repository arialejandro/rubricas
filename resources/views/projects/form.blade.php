@php
    // Tras un error de validación se reconstruyen los renglones con lo que la maestra escribió.
    $rows = old('criteria', $project->criteria->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'weight' => rtrim(rtrim(number_format($c->weight, 2, '.', ''), '0'), '.'),
        'graded' => $c->grades_count ?? 0,
    ])->all());
    if (empty($rows)) {
        $rows = [['id' => null, 'name' => '', 'weight' => '']];
    }
@endphp

<x-layouts.app :title="$project->exists ? 'Editar proyecto' : 'Nuevo proyecto'">
    <x-slot:breadcrumbs>
        <a href="{{ route('dashboard') }}" class="hover:text-brand-700">Mis grupos</a>
        <span>/</span><a href="{{ route('grupos.show', $group) }}" class="hover:text-brand-700">{{ $group->name }}</a>
        @if ($project->exists)
            <span>/</span><a href="{{ route('projects.show', [$group, $project]) }}" class="hover:text-brand-700">{{ $project->name }}</a>
        @endif
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-2xl space-y-6">
        <form method="POST" action="{{ $project->exists ? route('projects.update', [$group, $project]) : route('projects.store', $group) }}" class="space-y-6">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <section class="card space-y-4 p-6">
                <h1 class="text-xl font-bold">{{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }}</h1>
                <div>
                    <label class="label" for="name">Nombre del proyecto</label>
                    <input class="input" id="name" name="name" value="{{ old('name', $project->name) }}" placeholder="Ej. Maqueta del sistema solar" required>
                </div>
                <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <div>
                        <label class="label" for="description">Descripción <span class="font-normal text-slate-400">(opcional)</span></label>
                        <input class="input" id="description" name="description" value="{{ old('description', $project->description) }}">
                    </div>
                    <div>
                        <label class="label" for="due_date">Fecha <span class="font-normal text-slate-400">(opcional)</span></label>
                        <input class="input" id="due_date" type="date" name="due_date" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </section>

            <section class="card space-y-4 p-6" data-criteria-editor>
                <div>
                    <h2 class="text-lg font-bold">Rúbrica</h2>
                    <p class="text-sm text-slate-500">Cada aspecto se califica de 0 a 10 y vale un porcentaje del proyecto. Los porcentajes deben sumar 100%.</p>
                </div>

                <div class="space-y-2" data-criteria-list>
                    @foreach ($rows as $i => $row)
                        @include('projects._criterion-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>

                <template>
                    @include('projects._criterion-row', ['i' => '__INDEX__', 'row' => ['id' => null, 'name' => '', 'weight' => '']])
                </template>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <button type="button" class="btn btn-ghost" data-add-criterion>+ Agregar aspecto</button>
                    <p class="text-sm">Total: <span class="text-lg font-bold" data-weight-total>0%</span></p>
                </div>

                @if ($project->exists)
                    <label class="flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                        <input type="checkbox" name="confirm_remove" value="1" class="mt-1 size-4">
                        Si quito un aspecto que ya tiene calificaciones, confirmo que se borren esas calificaciones.
                    </label>
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ $project->exists ? route('projects.show', [$group, $project]) : route('grupos.show', $group) }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary">Guardar proyecto</button>
            </div>
        </form>

        @if ($project->exists)
            <form method="POST" action="{{ route('projects.destroy', [$group, $project]) }}" class="card space-y-3 border-red-200 p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-red-700">Eliminar proyecto</h2>
                <label class="flex items-start gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="confirm" value="1" class="mt-1 size-4">
                    Entiendo que se borran todas las calificaciones de este proyecto.
                </label>
                <button class="btn btn-danger">Eliminar proyecto</button>
            </form>
        @endif
    </div>
</x-layouts.app>
