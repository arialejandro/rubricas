{{--
    Captura "punto de venta" (CaptureController): lista de alumnos + teclado.
    mode "levels" = criterio de proyecto (niveles 6–10 con descriptores); "score" = 0–10.
--}}
@use('App\Support\Campos')
@use('App\Support\Level')
@php
    $total = $book->students->count();
    $kind = $kind;
    $descriptorsJson = $kind === 'criterion' ? json_encode([$unit->id => $descriptors], JSON_UNESCAPED_UNICODE) : '{}';
@endphp
<x-layouts.app :title="$title" hide-term>
    <x-slot:breadcrumbs>
        <a href="{{ $back[0] }}" class="flex items-center gap-1 hover:text-primary"><x-icon name="chevron-left" class="size-4" /> {{ $back[1] }}</a>
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-3xl">
        @if (count($tabs) > 1)
            <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Qué calificar">
                @foreach ($tabs as $tab)
                    <a href="{{ $tab['url'] }}" @class(['flex min-h-11 max-w-64 shrink-0 items-center rounded-xl border px-3.5 text-sm font-semibold',
                        'border-primary bg-primary text-on-primary' => $tab['active'], 'border-line bg-surface hover:border-primary' => ! $tab['active']])
                       @if ($tab['active']) aria-current="page" @endif><span class="truncate">{{ $tab['label'] }}</span></a>
                @endforeach
            </nav>
        @endif

        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <x-campo-chip :campo="$campo" class="mb-1" />
                <h1 class="text-2xl font-bold leading-tight sm:text-3xl">{{ $title }}</h1>
                <p class="text-ink-muted">{{ $subtitle }} · Trimestre {{ $term }}</p>
            </div>
            <p class="text-right">
                <span class="block text-2xl font-bold tabular-nums" data-paint="count:{{ $unitKey }}">{{ $total - $missing }}/{{ $total }}</span>
                <span class="lvl-text text-sm font-semibold" data-paint="missing:{{ $unitKey }}" data-level="{{ $missing ? 'pending' : 'done' }}">{{ $missing ? "{$missing} sin calificar" : 'Completo' }}</span>
            </p>
        </div>
        <x-progress :value="(int) floor(($total - $missing) * 100 / max(1, $total))" :bar="$unitKey" class="mb-4" />

        {{-- La rúbrica a la vista mientras se califica --}}
        @if ($descriptors)
            <details class="card group mb-4" open>
                <summary class="flex min-h-12 list-none items-center gap-2 px-4 text-sm font-semibold">
                    Rúbrica del criterio <x-icon name="chevron-right" class="ml-auto size-4 transition group-open:rotate-90" />
                </summary>
                <dl class="grid gap-2 border-t border-line p-3 sm:grid-cols-2">
                    @foreach ($descriptors as $lvl => $text)
                        <div class="rounded-xl p-3" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                            <dt class="text-sm font-bold" style="color: var(--lvl)">{{ Level::label($lvl) }} · {{ implode('/', Level::LEVELS[$lvl]['scores']) }}</dt>
                            <dd class="text-sm">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        @endif

        <div class="sticky top-[66px] z-20 -mx-4 mb-3 bg-canvas/95 px-4 py-2 backdrop-blur">
            <x-search pending-toggle="Sin calificar" placeholder="Apellido, nombre o N.L. y Enter" />
        </div>

        <ul class="card divide-y divide-line" data-score-url="{{ route('score.update', $group) }}" data-descriptors="{{ $descriptorsJson }}">
            @foreach ($book->students as $s)
                @php($score = $value($s))
                <li class="flex min-h-16 items-center gap-3 px-4 py-2"
                    data-search-item data-search-text="{{ $s->name }}" data-search-number="{{ $s->list_number }}"
                    data-done="{{ $score === null ? 0 : 1 }}" data-done-by="cell">
                    <span class="w-7 text-right text-sm font-semibold tabular-nums text-ink-muted">{{ $s->list_number }}</span>
                    <span class="min-w-0 flex-1 text-base font-medium">{{ $s->name }}</span>
                    <x-score :kind="$kind" :id="$unit->id" :term="$kind === 'subject' ? $term : null" :student="$s" :unit="$title" :value="$score" class="min-h-14 min-w-20 text-xl" />
                </li>
            @endforeach
            <li class="p-6 text-center text-ink-muted" data-search-empty hidden>Ningún alumno coincide.</li>
        </ul>
    </div>

    <x-slot:after><x-keypad /></x-slot:after>
</x-layouts.app>
