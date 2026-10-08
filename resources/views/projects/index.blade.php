<x-layouts.app title="Proyectos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Proyectos</h1>
            <p class="text-ink-muted">{{ $group->label() }} · {{ $overview->projects->count() }} en total</p>
        </div>
        <div class="flex gap-2">
            @if ($overview->projects->isNotEmpty())
                <a href="{{ route('export.group', $group) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Excel</a>
            @endif
            <a href="{{ route('projects.create', $group) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo proyecto</a>
        </div>
    </div>

    @if ($overview->students->isEmpty())
        <div class="card mb-5 flex flex-wrap items-center gap-3 border-pending/40 bg-pending-soft p-4 text-pending">
            <x-icon name="alert" />
            <p class="flex-1 font-semibold">Aún no hay alumnos en el grupo.</p>
            <a href="{{ route('students.index', $group) }}" class="btn btn-primary">Cargar lista</a>
        </div>
    @endif

    @if ($overview->projects->isEmpty())
        <div class="card flex flex-col items-center gap-3 p-10 text-center">
            <span class="grid size-14 place-items-center rounded-2xl bg-primary-soft text-primary"><x-icon name="folder" class="size-7" /></span>
            <h2 class="text-lg font-bold">Crea tu primer proyecto</h2>
            <p class="max-w-md text-ink-muted">Define sus aspectos de evaluación y cuánto vale cada uno.</p>
            <a href="{{ route('projects.create', $group) }}" class="btn btn-primary">Nuevo proyecto</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($overview->projects->sortByDesc('updated_at') as $project)
                @include('projects._tile', ['project' => $project, 's' => $overview->projectSummary($project)])
            @endforeach
        </div>
    @endif
</x-layouts.app>
