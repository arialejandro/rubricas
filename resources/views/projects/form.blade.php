@use('App\Support\Campos')
@php
    $pdas = old('pdas', $project->pdas ?: ['']);
@endphp
<x-layouts.app :title="$project->exists ? 'Editar proyecto' : 'Nuevo proyecto'" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ $project->exists ? route('projects.show', [$group, $project]) : route('projects.index', $group) }}" class="flex items-center gap-1 hover:text-primary">
            <x-icon name="chevron-left" class="size-4" /> {{ $project->exists ? $project->name : 'Proyectos' }}
        </a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }}</h1>
            <p class="text-ink-muted">Trimestre {{ $project->term }}</p>
        </div>

        <form method="POST" action="{{ $project->exists ? route('projects.update', [$group, $project]) : route('projects.store', $group) }}" class="space-y-6">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <section class="card space-y-4 p-5 sm:p-6">
                <div>
                    <label class="label" for="name">Nombre del proyecto</label>
                    <input class="input text-lg" id="name" name="name" value="{{ old('name', $project->name) }}" placeholder="El periódico escolar" required @unless ($project->exists) autofocus @endunless>
                </div>
                <fieldset>
                    <legend class="label">Campo formativo donde se plantea</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Campos::ALL as $key => $c)
                            <label class="flex min-h-14 items-center gap-2 rounded-2xl border-2 border-line px-3 text-sm font-semibold has-checked:border-[var(--c)] has-checked:bg-[color-mix(in_srgb,var(--c)_12%,transparent)]" style="--c: {{ $c['color'] }}">
                                <input type="radio" name="campo" value="{{ $key }}" class="sr-only" @checked(old('campo', $project->campo) === $key) required>
                                <span class="size-3 shrink-0 rounded-full" style="background: var(--c)"></span>{{ $c['name'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="hint">Cada producto del proyecto puede evaluarse en otro campo y sumará a ese campo.</p>
                </fieldset>
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

            <section class="card space-y-4 p-5 sm:p-6" data-repeater data-max="{{ \App\Models\Project::MAX_PDAS }}">
                <div>
                    <h2 class="text-lg font-bold">PDA</h2>
                    <p class="text-sm text-ink-muted">Procesos de Desarrollo de Aprendizaje que trabaja el proyecto (de 1 a {{ \App\Models\Project::MAX_PDAS }}).</p>
                </div>
                <div class="space-y-2" data-repeater-list>
                    @foreach ($pdas as $i => $pda)
                        @include('projects._pda-row', ['i' => $i, 'value' => $pda])
                    @endforeach
                </div>
                <template>@include('projects._pda-row', ['i' => '__INDEX__', 'value' => ''])</template>
                <button type="button" class="btn btn-ghost" data-repeater-add><x-icon name="plus" class="size-4" /> Agregar PDA</button>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ $project->exists ? route('projects.show', [$group, $project]) : route('projects.index', $group) }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary min-w-44">{{ $project->exists ? 'Guardar' : 'Crear y agregar productos' }}</button>
            </div>
        </form>

        @if ($project->exists)
            <form method="POST" action="{{ route('projects.destroy', [$group, $project]) }}" class="card space-y-3 border-danger/30 p-5 sm:p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-danger">Eliminar proyecto</h2>
                <label class="flex items-start gap-3 text-sm text-ink-muted">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-5">
                    Entiendo que se borran sus productos y todas sus calificaciones.
                </label>
                <button class="btn btn-danger">Eliminar proyecto</button>
            </form>
        @endif
    </div>
</x-layouts.app>
