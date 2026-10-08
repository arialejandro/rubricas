<x-layouts.app title="Campos formativos">
    <div class="mb-5">
        <h1 class="text-2xl font-bold sm:text-3xl">Campos formativos</h1>
        <p class="text-ink-muted">Trimestre {{ $book->term }} · {{ $group->label() }}. Cada campo tiene sus aspectos con porcentaje; las materias adicionales promedian con el campo.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (\App\Support\Campos::keys() as $campo)
            @include('campos._card')
        @endforeach
    </div>

    <x-level-legend class="mt-6" />
</x-layouts.app>
