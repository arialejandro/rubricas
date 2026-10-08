<x-layouts.app :title="$group->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('dashboard') }}" class="hover:text-brand-700">Mis grupos</a>
    </x-slot:breadcrumbs>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $group->name }}</h1>
            <p class="text-sm text-slate-500">{{ $group->school_year }} · {{ $studentCount }} {{ Str::plural('alumno', $studentCount) }} activos</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('students.index', $group) }}" class="btn btn-ghost">👥 Alumnos</a>
            <a href="{{ route('grupos.edit', $group) }}" class="btn btn-ghost">Editar</a>
            @if ($projects->isNotEmpty())
                <a href="{{ route('export.group', $group) }}" class="btn btn-ghost">⬇ Excel del grupo</a>
            @endif
        </div>
    </div>

    @if ($studentCount === 0)
        <div class="card mb-5 border-amber-200 bg-amber-50 p-5">
            <p class="font-semibold text-amber-900">Este grupo aún no tiene alumnos.</p>
            <p class="mb-3 text-sm text-amber-800">Agrégalos primero; puedes pegar la lista completa de una vez.</p>
            <a href="{{ route('students.index', $group) }}" class="btn btn-primary">Agregar alumnos</a>
        </div>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold">Proyectos</h2>
        <a href="{{ route('projects.create', $group) }}" class="btn btn-primary">+ Nuevo proyecto</a>
    </div>

    @if ($projects->isEmpty())
        <div class="card p-8 text-center text-slate-500">Aún no hay proyectos. Crea uno y define su rúbrica.</div>
    @else
        <div class="space-y-3">
            @foreach ($projects as $project)
                @php($s = $summary[$project->id])
                <a href="{{ route('projects.show', [$group, $project]) }}" class="card block p-4 transition hover:border-brand-600 sm:p-5">
                    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold">{{ $project->name }}</h3>
                            <p class="text-sm text-slate-500">
                                {{ $project->criteria_count }} {{ Str::plural('aspecto', $project->criteria_count) }}
                                @if ($project->due_date) · {{ $project->due_date->translatedFormat('j M Y') }} @endif
                            </p>
                        </div>
                        @if ($s['total'] > 0 && $s['missing'] === 0)
                            <span class="chip bg-emerald-100 text-emerald-800">Completo</span>
                        @elseif ($s['total'] > 0)
                            <span class="chip bg-amber-100 text-amber-800">Faltan {{ $s['missing'] }}</span>
                        @endif
                    </div>
                    <x-progress :graded="$s['graded']" :total="$s['total']" />
                    <p class="mt-1 text-xs text-slate-500">{{ $s['graded'] }} de {{ $s['total'] }} calificaciones</p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
