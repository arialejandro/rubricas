@use('App\Support\Campos')
@use('App\Support\Level')
@use('App\Support\TermBook')
@php
    $p = $book->projectProgress($project);
    $total = $book->students->count();
    $criteria = $project->products->flatMap->criteria;
    $descriptors = $criteria->mapWithKeys(fn ($c) => [$c->id => $c->descriptors()]);
@endphp
<x-layouts.app :title="$project->name" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ route('projects.index', $group) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> Proyectos · T{{ $project->term }}</a>
    </x-slot:breadcrumbs>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="mb-1 flex flex-wrap gap-1">
                @foreach ($project->campos() as $c)
                    <x-campo-chip :campo="$c" />
                @endforeach
            </div>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $project->name }}</h1>
            <p class="text-ink-muted">
                Planteado en {{ Campos::name($project->campo) }}
                @if ($project->due_date) · {{ $project->due_date->translatedFormat('j \d\e F') }} @endif
            </p>
        </div>
        <a href="{{ route('projects.edit', [$group, $project]) }}" class="btn btn-ghost"><x-icon name="pencil" class="size-4" /> Editar</a>
    </div>

    @if ($project->description || $project->pdas)
        <section class="card mb-5 p-4 sm:p-5">
            @if ($project->description) <p class="mb-3">{{ $project->description }}</p> @endif
            @if ($project->pdas)
                <h2 class="mb-2 text-sm font-bold text-ink-muted">PDA</h2>
                <ol class="list-inside list-decimal space-y-1 text-sm">
                    @foreach ($project->pdas as $pda) <li>{{ $pda }}</li> @endforeach
                </ol>
            @endif
        </section>
    @endif

    {{-- Productos → criterios: un tap y a calificar --}}
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-bold">Productos</h2>
        <a href="{{ route('products.create', [$group, $project]) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Producto</a>
    </div>

    @if ($project->products->isEmpty())
        <div class="card mb-6 p-8 text-center text-ink-muted">Agrega el primer producto: el campo donde se evalúa, su instrumento y los criterios a observar.</div>
    @else
        <div class="mb-6 space-y-4">
            @foreach ($project->products as $product)
                <section class="card p-4 sm:p-5" style="--c: {{ Campos::color($product->campo) }}">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <x-campo-chip :campo="$product->campo" />
                            <h3 class="mt-1 text-lg font-bold">{{ $product->name }}</h3>
                            @if ($product->instrument) <p class="text-sm text-ink-muted">Instrumento: {{ $product->instrument }}</p> @endif
                        </div>
                        <a href="{{ route('products.edit', [$group, $project, $product]) }}" class="btn btn-ghost"><x-icon name="pencil" class="size-4" /> Editar</a>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($product->criteria as $c)
                            @php($m = $book->criterionMissing($c))
                            <a href="{{ route('capture.criterion', [$group, $project, $product, $c]) }}" class="tile min-h-28 p-3.5">
                                <p class="line-clamp-3 font-semibold leading-snug">{{ $c->description }}</p>
                                <div class="mt-auto">
                                    <div class="mb-1 flex justify-between text-xs">
                                        <span class="lvl-text font-semibold" data-paint="missing:criterion:{{ $c->id }}" data-level="{{ $m ? 'pending' : 'done' }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
                                        <span class="tabular-nums text-ink-muted" data-paint="count:criterion:{{ $c->id }}">{{ $total - $m }}/{{ $total }}</span>
                                    </div>
                                    <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" bar="criterion:{{ $c->id }}" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Matriz: alumnos × criterios, agrupados por producto --}}
        @if ($criteria->isNotEmpty() && $book->students->isNotEmpty())
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">Todas las calificaciones</h2>
                <x-level-legend />
            </div>
            <x-search class="mb-3" />

            <section class="card overflow-x-auto" data-score-url="{{ route('score.update', $group) }}" data-advance="column"
                     data-descriptors="{{ json_encode($descriptors, JSON_UNESCAPED_UNICODE) }}">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-ink-muted">
                            <th class="sticky left-0 z-10 bg-surface" rowspan="2"></th>
                            @foreach ($project->products as $product)
                                <th colspan="{{ $product->criteria->count() + 1 }}" class="border-b border-line px-2 pt-3 pb-1 text-center" style="--c: {{ Campos::color($product->campo) }}">
                                    <span class="campo-chip">{{ $product->name }}</span>
                                </th>
                            @endforeach
                        </tr>
                        <tr class="border-b border-line text-ink-muted">
                            @foreach ($project->products as $product)
                                @foreach ($product->criteria as $i => $c)
                                    <th class="px-2 py-2 text-center text-xs font-semibold" title="{{ $c->description }}"><span class="block max-w-28 truncate">{{ $i + 1 }}. {{ $c->description }}</span></th>
                                @endforeach
                                <th class="px-2 py-2 text-center text-xs font-semibold text-ink">Prom.</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($book->students as $s)
                            <tr data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}">
                                <td class="sticky left-0 z-10 min-w-52 bg-surface px-4 py-2">
                                    <span class="mr-1 inline-block w-6 text-right text-xs tabular-nums text-ink-muted">{{ $s->list_number }}</span>
                                    <a href="{{ route('students.show', [$group, $s]) }}" class="font-medium hover:text-primary">{{ $s->name }}</a>
                                </td>
                                @foreach ($project->products as $product)
                                    @foreach ($product->criteria as $c)
                                        <td class="px-2 py-1.5 text-center"><x-score kind="criterion" :id="$c->id" :student="$s" :unit="$c->description" :value="$book->criterionScore($s->id, $c->id)" /></td>
                                    @endforeach
                                    @php($ps = $book->productScore($product, $s))
                                    <td class="lvl-text px-2 text-center font-bold tabular-nums" data-paint="product:{{ $product->id }}:{{ $s->id }}"
                                        data-level="{{ $book->productMissing($product, $s) ? 'provisional' : Level::of($ps) }}">{{ TermBook::fmt($ps) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</p>
            </section>
        @endif
    @endif

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
