@php($fmt = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($m = $book->missingForCriterion($criterion))
@php($total = $book->students->count())
<x-layouts.app title="{{ $criterion->name }} · {{ $project->name }}">
    <x-slot:breadcrumbs>
        <a href="{{ route('projects.show', [$group, $project]) }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> {{ $project->name }}</a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-3xl">
        {{-- Cambiar de aspecto sin salir: pestañas desplazables --}}
        <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Aspectos">
            @foreach ($book->criteria as $c)
                <a href="{{ route('capture', [$group, $project, $c]) }}" @class(['flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold',
                    'border-primary bg-primary text-on-primary' => $c->is($criterion), 'border-line bg-surface hover:border-primary' => ! $c->is($criterion)])
                   @if ($c->is($criterion)) aria-current="page" @endif>
                    {{ $c->name }} <span class="num opacity-75">{{ $fmt($c->weight) }}%</span>
                </a>
            @endforeach
        </nav>

        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ $criterion->name }}</h1>
                <p class="text-ink-muted">Vale <span class="num font-semibold text-ink">{{ $fmt($criterion->weight) }}%</span> del proyecto</p>
            </div>
            <p class="text-right">
                <span class="num block text-2xl font-bold" data-criterion-count="{{ $criterion->id }}">{{ $total - $m }}/{{ $total }}</span>
                <span class="text-sm font-semibold {{ $m ? 'text-pending' : 'text-done' }}" data-criterion-missing="{{ $criterion->id }}">{{ $m ? "{$m} sin calificar" : 'Completo' }}</span>
            </p>
        </div>
        <x-progress :value="(int) floor(($total - $m) * 100 / max(1, $total))" :criterion="$criterion->id" class="mb-4" />

        <div class="sticky top-[66px] z-20 -mx-4 mb-3 bg-canvas/95 px-4 py-2 backdrop-blur">
            <x-search pending-toggle="Sin calificar" placeholder="Apellido, nombre o N.L. y Enter" />
        </div>

        <ul class="card divide-y divide-line" data-grades-url="{{ route('grades.update', [$group, $project]) }}">
            @foreach ($book->students as $s)
                @php($score = $book->score($s->id, $criterion->id))
                <li class="flex min-h-16 items-center gap-3 px-4 py-2"
                    data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}"
                    data-done="{{ $score === null ? 0 : 1 }}" data-done-by="cell">
                    <span class="num w-7 text-right text-sm font-semibold text-ink-muted">{{ $s->list_number }}</span>
                    <span class="min-w-0 flex-1 text-base font-medium">{{ $s->name }}</span>
                    <x-score :student="$s" :criterion="$criterion" :value="$score" class="min-h-14 min-w-20 text-xl" />
                </li>
            @endforeach
            <li class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</li>
        </ul>

        <div class="mt-4 flex justify-between gap-2">
            @if ($prev)
                <a href="{{ route('capture', [$group, $project, $prev]) }}" class="btn btn-ghost"><x-icon name="chevron-left" class="size-4" /> {{ $prev->name }}</a>
            @else <span></span> @endif
            @if ($next)
                <a href="{{ route('capture', [$group, $project, $next]) }}" class="btn btn-primary">{{ $next->name }} <x-icon name="chevron-right" class="size-4" /></a>
            @else
                <a href="{{ route('projects.show', [$group, $project]) }}" class="btn btn-primary">Ver resultados <x-icon name="chevron-right" class="size-4" /></a>
            @endif
        </div>
    </div>

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
