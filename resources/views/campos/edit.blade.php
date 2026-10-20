@use('App\Support\Campos')
@use('App\Models\TermAspect')
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $rows = old('aspects', $aspects->isNotEmpty()
        ? $aspects->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'type' => $a->type, 'weight' => $fmt($a->weight), 'graded' => $a->scores_count])->all()
        : Campos::defaultAspects($campo));
    $subjectRows = old('subjects', $subjects->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all());
    $skillRows = old('skills', $skills->map(fn ($k) => [
        'id' => $k->id, 'name' => $k->name, 'graded' => $k->scores_count, 'grading' => $k->grading,
        'criteria' => $k->criteria->map(fn ($c) => $c->only(['id', 'description', 'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo']))->all(),
        'measure_unit' => $k->measure_unit, 'lower_is_better' => $k->lower_is_better,
        'min_7' => $k->min_7, 'min_8' => $k->min_8, 'min_9' => $k->min_9, 'min_10' => $k->min_10,
        'level_labels' => $k->level_labels ?? [],
        'level_logrado' => $k->level_logrado, 'level_satisfactorio' => $k->level_satisfactorio,
        'level_proceso' => $k->level_proceso, 'level_apoyo' => $k->level_apoyo,
    ])->all());
@endphp
<x-layouts.app :title="'Evaluación · '.Campos::short($campo)" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ route('campos.show', [$group, $campo]) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> {{ Campos::name($campo) }}</a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <x-campo-chip :campo="$campo" :short="false" />
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Evaluación del Trimestre {{ $term }}</h1>
            <p class="text-ink-muted">Define entre 3 y 6 aspectos con su porcentaje (deben sumar 100%). Pueden cambiar cada trimestre.</p>
        </div>

        @if ($canCopy)
            <form method="POST" action="{{ route('campos.copy', [$group, $campo]) }}" class="card flex flex-wrap items-center gap-3 p-4">
                @csrf
                <p class="flex-1 text-sm">¿Usas la misma estrategia que el Trimestre {{ $term - 1 }}?</p>
                <button class="btn btn-ghost">Copiar del Trimestre {{ $term - 1 }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('campos.update', [$group, $campo]) }}" class="space-y-6">
            @csrf @method('PUT')

            <section class="card space-y-4 p-5 sm:p-6" data-repeater data-max="{{ Campos::MAX_ASPECTS }}">
                <div>
                    <h2 class="text-lg font-bold">Aspectos</h2>
                    <p class="text-sm text-ink-muted"><strong>De los proyectos</strong>: toma el promedio de los productos evaluados en este campo, de cualquier proyecto. <strong>Captura directa</strong>: se califica del 0 al 10 (examen, tareas…).
                        @if (Campos::hasSkills($campo)) <strong>Habilidades prioritarias</strong>: promedio de las habilidades de abajo, calificadas por nivel. @endif</p>
                </div>

                <div class="space-y-3" data-repeater-list>
                    @foreach ($rows as $i => $row)
                        @include('campos._aspect-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <template>@include('campos._aspect-row', ['i' => '__INDEX__', 'row' => ['type' => 'direct']])</template>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-ghost" data-repeater-add><x-icon name="plus" class="size-4" /> Agregar aspecto</button>
                    <button type="button" class="btn btn-ghost" data-split-even>Repartir igual</button>
                </div>
                <div class="rounded-xl bg-surface-2 p-3">
                    <div class="mb-1.5 flex items-center justify-between text-sm">
                        <span class="font-medium">Total (debe ser 100%)</span>
                        <span class="text-lg font-bold tabular-nums" data-weight-total>0%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full transition-[width]" data-weight-bar></div></div>
                </div>
            </section>

            @if (Campos::hasSkills($campo))
                <section class="card space-y-4 p-5 sm:p-6" data-repeater data-max="8">
                    <div>
                        <h2 class="text-lg font-bold">Habilidades prioritarias</h2>
                        <p class="text-sm text-ink-muted">{{ $campo === 'lenguajes' ? 'Lectura y escritura: velocidad, fluidez, comprensión…' : 'Matemáticas: cálculo mental, resolución de problemas…' }}
                            Se califican por nivel (Logrado 10, Satisfactorio 9, En proceso 8/7, Requiere apoyo 6) y suman al campo a través del aspecto "Habilidades prioritarias". Son las mismas en los tres trimestres.</p>
                    </div>
                    <div class="space-y-3" data-repeater-list>
                        @foreach ($skillRows as $i => $row)
                            @include('campos._skill-row', ['i' => $i, 'row' => $row])
                        @endforeach
                    </div>
                    <template>@include('campos._skill-row', ['i' => '__INDEX__', 'row' => []])</template>
                    <datalist id="measure-units">
                        <option value="palabras por minuto"><option value="aciertos"><option value="errores"><option value="operaciones correctas"><option value="segundos">
                    </datalist>
                    <button type="button" class="btn btn-ghost" data-repeater-add><x-icon name="plus" class="size-4" /> Agregar habilidad</button>
                </section>
            @endif

            <section class="card space-y-4 p-5 sm:p-6" data-repeater data-max="4">
                <div>
                    <h2 class="text-lg font-bold">Materias adicionales</h2>
                    <p class="text-sm text-ink-muted">Llegan solo con su calificación y promedian en partes iguales con el campo. Son las mismas en los tres trimestres.</p>
                </div>
                <div class="space-y-2" data-repeater-list>
                    @foreach ($subjectRows as $i => $row)
                        @include('campos._subject-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <template>@include('campos._subject-row', ['i' => '__INDEX__', 'row' => []])</template>
                <button type="button" class="btn btn-ghost" data-repeater-add><x-icon name="plus" class="size-4" /> Agregar materia</button>
            </section>

            <label class="flex items-start gap-3 rounded-xl border border-line bg-surface p-3 text-sm text-ink-muted">
                <input type="checkbox" name="confirm_remove" value="1" class="mt-0.5 size-5">
                Si quito un aspecto, habilidad o materia que ya tiene calificaciones, confirmo que se borren.
            </label>

            <div class="flex justify-end gap-2">
                <a href="{{ route('campos.show', [$group, $campo]) }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary min-w-44">Guardar</button>
            </div>
        </form>
    </div>
</x-layouts.app>
