<x-layouts.app title="Proyectos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Proyectos</h1>
            <p class="text-ink-muted">Trimestre {{ $book->term }} · {{ $book->projects->count() }} {{ $book->projects->count() === 1 ? 'proyecto' : 'proyectos' }}</p>
        </div>
        <a href="{{ route('projects.create', $group) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo proyecto</a>
    </div>

    @if ($book->projects->isEmpty())
        <div class="card flex flex-col items-center gap-3 p-10 text-center">
            <span class="grid size-14 place-items-center rounded-2xl bg-primary-soft text-primary"><x-icon name="folder" class="size-7" /></span>
            <h2 class="text-lg font-bold">Crea el primer proyecto del trimestre</h2>
            <p class="max-w-md text-ink-muted">Elige el campo donde se plantea, describe sus PDA y agrega sus productos con los criterios a observar. Cada producto suma al campo que le asignes.</p>
            <a href="{{ route('projects.create', $group) }}" class="btn btn-primary">Nuevo proyecto</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($book->projects->sortByDesc('updated_at') as $project)
                @include('projects._tile')
            @endforeach
        </div>
    @endif
</x-layouts.app>
