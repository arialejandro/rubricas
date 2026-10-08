<x-layouts.app title="Mis grupos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Mis grupos</h1>
        <a href="{{ route('grupos.create') }}" class="btn btn-primary">+ Nuevo grupo</a>
    </div>

    @if ($groups->isEmpty())
        <div class="card p-8 text-center">
            <p class="mb-1 text-lg font-semibold">Empieza creando tu primer grupo</p>
            <p class="mb-5 text-slate-500">Un grupo es un salón: ahí agregas a tus alumnos y sus proyectos.</p>
            <a href="{{ route('grupos.create') }}" class="btn btn-primary">Crear grupo</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($groups as $group)
                <a href="{{ route('grupos.show', $group) }}" class="card block p-5 transition hover:border-brand-600 hover:shadow-md">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-bold">{{ $group->name }}</h2>
                            @if ($group->school_year)
                                <p class="text-sm text-slate-500">{{ $group->school_year }}</p>
                            @endif
                        </div>
                        @if ($missing[$group->id] > 0)
                            <span class="chip bg-amber-100 text-amber-800">{{ $missing[$group->id] }} pendientes</span>
                        @elseif ($group->projects_count > 0)
                            <span class="chip bg-emerald-100 text-emerald-800">Al día</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-600">
                        {{ $group->active_students_count }} {{ Str::plural('alumno', $group->active_students_count) }}
                        · {{ $group->projects_count }} {{ Str::plural('proyecto', $group->projects_count) }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
