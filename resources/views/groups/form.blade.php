<x-layouts.app :title="$group->exists ? 'Centro de trabajo' : 'Nuevo grupo'">
    @if ($group->exists)
        <x-slot:breadcrumbs>
            <a href="{{ route('grupos.show', $group) }}" class="hover:text-primary">Inicio</a>
        </x-slot:breadcrumbs>
    @endif

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $group->exists ? 'Centro de trabajo' : ($taken->isEmpty() ? 'Configura tu grupo' : 'Agrega tu grupo '.strtolower($group->shiftLabel())) }}</h1>
            <p class="mt-1 text-ink-muted">Estos datos aparecen en tu inicio y en los encabezados del Excel.</p>
        </div>

        <form method="POST" action="{{ $group->exists ? route('grupos.update', $group) : route('grupos.store') }}" class="space-y-6">
            @csrf
            @if ($group->exists) @method('PUT') @endif

            <fieldset class="card space-y-4 p-5 sm:p-6">
                <legend class="sr-only">Turno</legend>
                <p class="label">Turno</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (\App\Models\Group::SHIFTS as $value => $label)
                        @php($disabled = $taken->contains($value))
                        <label @class(['flex min-h-16 items-center gap-3 rounded-2xl border-2 px-4 font-semibold transition',
                            'has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary border-line' => ! $disabled,
                            'cursor-not-allowed border-line opacity-45' => $disabled])>
                            <input type="radio" name="shift" value="{{ $value }}" class="sr-only" @checked(old('shift', $group->shift) === $value) @disabled($disabled) required>
                            <x-icon :name="$value === 'matutino' ? 'sun' : 'sunset'" class="size-6" />
                            <span>{{ $label }} @if ($disabled)<span class="block text-xs font-normal">Ya registrado</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="card grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <legend class="sr-only">Grupo</legend>
                <div>
                    <label class="label" for="grade">Grado</label>
                    <div class="grid grid-cols-6 gap-1.5">
                        @foreach (range(1, 6) as $g)
                            <label class="grid min-h-12 place-items-center rounded-xl border-2 border-line text-lg font-bold has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary">
                                <input type="radio" name="grade" value="{{ $g }}" class="sr-only" @checked((int) old('grade', $group->grade) === $g) required>{{ $g }}°
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label" for="name">Grupo</label>
                    <input class="input text-lg font-bold uppercase" id="name" name="name" value="{{ old('name', $group->name) }}" placeholder="A" maxlength="10" required autocapitalize="characters">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="school_year">Ciclo escolar</label>
                    <input class="input" id="school_year" name="school_year" value="{{ old('school_year', $group->school_year ?? now()->year.'-'.(now()->year + 1)) }}" placeholder="2026-2027">
                </div>
            </fieldset>

            <fieldset class="card grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <legend class="sr-only">Escuela</legend>
                <div class="sm:col-span-2">
                    <label class="label" for="school_name">Nombre de la escuela</label>
                    <input class="input" id="school_name" name="school_name" value="{{ old('school_name', $group->school_name) }}" placeholder="Esc. Prim. Benito Juárez" required>
                </div>
                <div>
                    <label class="label" for="school_cct">Clave del centro de trabajo (CCT)</label>
                    <input class="input uppercase" id="school_cct" name="school_cct" value="{{ old('school_cct', $group->school_cct) }}" placeholder="09DPR1234X" autocapitalize="characters">
                </div>
                <div>
                    <label class="label" for="school_zone">Zona escolar</label>
                    <input class="input" id="school_zone" name="school_zone" value="{{ old('school_zone', $group->school_zone) }}" placeholder="015" inputmode="numeric">
                </div>
            </fieldset>

            <div class="flex justify-end gap-2">
                @if ($group->exists)
                    <a href="{{ route('grupos.show', $group) }}" class="btn btn-ghost">Cancelar</a>
                @endif
                <button class="btn btn-primary min-w-40">{{ $group->exists ? 'Guardar' : 'Crear grupo' }}</button>
            </div>
        </form>

        @if ($group->exists)
            <form method="POST" action="{{ route('grupos.destroy', $group) }}" class="card space-y-3 border-danger/30 p-5 sm:p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-danger">Eliminar grupo</h2>
                <p class="text-sm text-ink-muted">Se borran sus alumnos, proyectos y todas las calificaciones. No se puede deshacer; descarga antes el Excel si lo necesitas.</p>
                <label class="label" for="confirm_name">Escribe <strong>{{ $group->label() }}</strong> para confirmar</label>
                <input class="input" id="confirm_name" name="confirm_name" autocomplete="off">
                <button class="btn btn-danger">Eliminar definitivamente</button>
            </form>
        @endif
    </div>
</x-layouts.app>
