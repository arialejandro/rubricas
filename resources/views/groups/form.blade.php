<x-layouts.app :title="$group->exists ? 'Editar grupo' : 'Nuevo grupo'">
    <x-slot:breadcrumbs>
        <a href="{{ route('dashboard') }}" class="hover:text-brand-700">Mis grupos</a>
        @if ($group->exists)
            <span>/</span><a href="{{ route('grupos.show', $group) }}" class="hover:text-brand-700">{{ $group->name }}</a>
        @endif
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-lg space-y-6">
        <form method="POST" action="{{ $group->exists ? route('grupos.update', $group) : route('grupos.store') }}" class="card space-y-4 p-6">
            @csrf
            @if ($group->exists) @method('PUT') @endif
            <h1 class="text-xl font-bold">{{ $group->exists ? 'Editar grupo' : 'Nuevo grupo' }}</h1>
            <div>
                <label class="label" for="name">Nombre del grupo</label>
                <input class="input" id="name" name="name" value="{{ old('name', $group->name) }}" placeholder="Ej. 3°B" required autofocus>
            </div>
            <div>
                <label class="label" for="school_year">Ciclo escolar <span class="font-normal text-slate-400">(opcional)</span></label>
                <input class="input" id="school_year" name="school_year" value="{{ old('school_year', $group->school_year) }}" placeholder="Ej. 2026-2027">
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ $group->exists ? route('grupos.show', $group) : route('dashboard') }}" class="btn btn-ghost">Cancelar</a>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>

        @if ($group->exists)
            <form method="POST" action="{{ route('grupos.destroy', $group) }}" class="card space-y-3 border-red-200 p-6">
                @csrf @method('DELETE')
                <h2 class="font-bold text-red-700">Eliminar grupo</h2>
                <p class="text-sm text-slate-600">Se borran sus alumnos, proyectos y todas las calificaciones. No se puede deshacer. Descarga antes el Excel si lo necesitas.</p>
                <input class="input" name="confirm_name" placeholder="Escribe: {{ $group->name }}" autocomplete="off">
                <button class="btn btn-danger">Eliminar definitivamente</button>
            </form>
        @endif
    </div>
</x-layouts.app>
