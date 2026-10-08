@use('App\Support\Campos')
@php
    $rows = old('criteria', $product->criteria->map(fn ($c) => [
        'id' => $c->id, 'description' => $c->description, 'graded' => $c->scores_count ?? 0,
        'level_logrado' => $c->level_logrado, 'level_satisfactorio' => $c->level_satisfactorio,
        'level_proceso' => $c->level_proceso, 'level_apoyo' => $c->level_apoyo,
    ])->all());
    if (empty($rows)) {
        $rows = [[]];
    }
@endphp
<x-layouts.app :title="$product->exists ? 'Editar producto' : 'Nuevo producto'" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ route('projects.show', [$group, $project]) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> {{ $project->name }}</a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>

        <form method="POST" action="{{ $product->exists ? route('products.update', [$group, $project, $product]) : route('products.store', [$group, $project]) }}" class="space-y-6">
            @csrf
            @if ($product->exists) @method('PUT') @endif

            <section class="card space-y-4 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-[1fr_14rem]">
                    <div>
                        <label class="label" for="name">Producto</label>
                        <input class="input text-lg" id="name" name="name" value="{{ old('name', $product->name) }}" placeholder="Noticia escrita" required>
                    </div>
                    <div>
                        <label class="label" for="instrument">Instrumento</label>
                        <input class="input" id="instrument" name="instrument" value="{{ old('instrument', $product->instrument) }}" placeholder="Rúbrica" list="instruments">
                        <datalist id="instruments">
                            <option value="Rúbrica"><option value="Lista de cotejo"><option value="Escala estimativa"><option value="Guía de observación"><option value="Registro anecdótico">
                        </datalist>
                    </div>
                </div>
                <fieldset>
                    <legend class="label">Campo formativo al que suma</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Campos::ALL as $key => $c)
                            <label class="flex min-h-14 items-center gap-2 rounded-2xl border-2 border-line px-3 text-sm font-semibold has-checked:border-[var(--c)] has-checked:bg-[color-mix(in_srgb,var(--c)_12%,transparent)]" style="--c: {{ $c['color'] }}">
                                <input type="radio" name="campo" value="{{ $key }}" class="sr-only" @checked(old('campo', $product->campo) === $key) required>
                                <span class="size-3 shrink-0 rounded-full" style="background: var(--c)"></span>{{ $c['name'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="hint">Si el proyecto es de {{ Campos::short($project->campo) }} pero este producto evalúa otro campo, elige ese campo: aparecerá ahí.</p>
                </fieldset>
            </section>

            <section class="card space-y-4 p-5 sm:p-6" data-repeater data-max="{{ \App\Http\Controllers\ProductController::MAX_CRITERIA }}">
                <div>
                    <h2 class="text-lg font-bold">Criterios a observar</h2>
                    <p class="text-sm text-ink-muted">Escribe qué se observa y, de la rúbrica, qué significa cada nivel. Al calificar se elige el nivel: Logrado 10, Satisfactorio 9, En proceso 8 o 7, Requiere apoyo 6.</p>
                </div>
                <div class="space-y-4" data-repeater-list>
                    @foreach ($rows as $i => $row)
                        @include('products._criterion-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <template>@include('products._criterion-row', ['i' => '__INDEX__', 'row' => []])</template>
                <button type="button" class="btn btn-ghost" data-repeater-add><x-icon name="plus" class="size-4" /> Agregar criterio</button>

                @if ($product->exists)
                    <label class="flex items-start gap-3 rounded-xl border border-line p-3 text-sm text-ink-muted">
                        <input type="checkbox" name="confirm_remove" value="1" class="mt-0.5 size-5">
                        Si quito un criterio que ya tiene calificaciones, confirmo que se borren.
                    </label>
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('projects.show', [$group, $project]) }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary min-w-44">Guardar producto</button>
            </div>
        </form>

        @if ($product->exists)
            <form method="POST" action="{{ route('products.destroy', [$group, $project, $product]) }}" class="card space-y-3 border-danger/30 p-5 sm:p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-danger">Eliminar producto</h2>
                <label class="flex items-start gap-3 text-sm text-ink-muted">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-5">
                    Entiendo que se borran sus criterios y calificaciones.
                </label>
                <button class="btn btn-danger">Eliminar producto</button>
            </form>
        @endif
    </div>
</x-layouts.app>
