{{--
    Celda de calificación: tap → teclado (resources/js/keypad.js). Vacío = pendiente (no es 0).
    kind: criterion | skill (nivel 6–10) | aspect | subject (0–10). unit = nombre de lo que se califica.
    Habilidad medible (mode="measure"): el botón muestra la medición (103) y a su lado va la
    calificación que le corresponde, fija (no se edita: sale de la medición y los rangos).
    `labels` (nivel => nombre) agrega el nombre del nivel junto a la calificación.
--}}
@props(['kind', 'id', 'student', 'unit', 'value' => null, 'term' => null, 'mode' => null, 'measure' => null, 'labels' => null])
@php
    $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $v = $fmt($value);
    $m = $fmt($measure);
    $mode ??= in_array($kind, ['criterion', 'skill'], true) ? 'levels' : 'score';
    $level = \App\Support\Level::of($value);
    $shown = $mode === 'measure' ? ($m !== '' ? $m : $v) : $v;
@endphp
@if ($mode === 'measure')<span class="inline-flex items-center gap-1.5">@endif
<button type="button" {{ $attributes->merge(['class' => 'score']) }}
        data-score-cell data-kind="{{ $kind }}" data-id="{{ $id }}" @if ($term) data-term="{{ $term }}" @endif
        data-mode="{{ $mode }}" @if ($mode === 'measure') data-measure="{{ $m }}" @endif
        data-student="{{ $student->id }}" data-student-name="{{ $student->name }}" data-unit-name="{{ $unit }}"
        data-value="{{ $v }}" @if ($v === '') data-empty @else data-level="{{ $level }}" @endif
        aria-label="{{ $student->name }}, {{ $unit }}: {{ $v === '' ? 'sin calificar' : ($mode === 'measure' ? $m.' → '.$v : $v) }}">{{ $shown === '' ? '—' : $shown }}</button>
@if ($mode === 'measure')
    <span class="lvl-chip {{ $labels ? 'min-w-32' : 'min-w-9' }} justify-center text-sm tabular-nums" data-measure-score @if ($labels) data-labels="{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}" @endif
          @if ($v !== '') data-level="{{ $level }}" @endif title="Calificación (se calcula de la medición)">{{ $v === '' ? '—' : $v.($labels ? ' · '.($labels[$level] ?? '') : '') }}</span>
</span>
@endif
