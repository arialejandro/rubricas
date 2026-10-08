<x-layouts.app title="Alumnos · {{ $group->name }}">
    <x-slot:breadcrumbs>
        <a href="{{ route('dashboard') }}" class="hover:text-brand-700">Mis grupos</a>
        <span>/</span><a href="{{ route('grupos.show', $group) }}" class="hover:text-brand-700">{{ $group->name }}</a>
    </x-slot:breadcrumbs>

    <h1 class="mb-5 text-2xl font-bold">Alumnos de {{ $group->name }}</h1>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden">
            @if ($students->isEmpty())
                <p class="p-8 text-center text-slate-500">Aún no hay alumnos. Agrégalos con el formulario.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($students as $student)
                        <li class="p-3 sm:px-4 {{ $student->active ? '' : 'bg-slate-50' }}">
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center gap-3">
                                    <span class="w-8 text-right text-sm tabular-nums text-slate-400">{{ $student->list_number }}</span>
                                    <span class="flex-1 font-medium {{ $student->active ? '' : 'text-slate-400 line-through' }}">{{ $student->name }}</span>
                                    @unless ($student->active)
                                        <span class="chip bg-slate-200 text-slate-600">Baja</span>
                                    @endunless
                                    <span class="text-sm text-brand-700 group-open:hidden">Editar</span>
                                </summary>

                                <form method="POST" action="{{ route('students.update', [$group, $student]) }}" class="mt-3 grid gap-3 sm:grid-cols-[5rem_1fr]">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="label">No.</label>
                                        <input class="input" name="list_number" type="number" inputmode="numeric" min="1" value="{{ $student->list_number }}">
                                    </div>
                                    <div>
                                        <label class="label">Nombre</label>
                                        <input class="input" name="name" value="{{ $student->name }}" required>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                                        <input type="hidden" name="active" value="{{ $student->active ? 1 : 0 }}">
                                        <button class="btn btn-primary">Guardar</button>
                                        @if ($student->active)
                                            <button class="btn btn-ghost" name="active" value="0">Dar de baja</button>
                                        @else
                                            <button class="btn btn-ghost" name="active" value="1">Reactivar</button>
                                        @endif
                                    </div>
                                </form>
                                @if ($student->grades_count === 0)
                                    <form method="POST" action="{{ route('students.destroy', [$group, $student]) }}" class="mt-2" data-confirm="¿Eliminar a {{ $student->name }}?">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-red-700 hover:underline">Eliminar (no tiene calificaciones)</button>
                                    </form>
                                @endif
                            </details>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside>
            <form method="POST" action="{{ route('students.store', $group) }}" class="card space-y-3 p-5 lg:sticky lg:top-20">
                @csrf
                <h2 class="font-bold">Agregar alumnos</h2>
                <p class="text-sm text-slate-500">Uno por renglón. Puedes pegar la lista desde Excel. Si empiezas con el número de lista se respeta: <span class="font-mono text-xs">12 Ana López</span></p>
                <textarea class="input min-h-48 font-mono text-sm" name="names" placeholder="1 Ana López Pérez&#10;2 Bruno Díaz&#10;3 Carla Méndez" required>{{ old('names') }}</textarea>
                <button class="btn btn-primary w-full">Agregar</button>
                <p class="text-xs text-slate-400">Los nombres repetidos no se duplican.</p>
            </form>
        </aside>
    </div>
</x-layouts.app>
