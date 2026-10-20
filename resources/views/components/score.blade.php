{{--
    Celda de calificación: tap → teclado (resources/js/keypad.js). Vacío = pendiente (no es 0).
    kind: criterion | skill (nivel 6–10) | aspect | subject (0–10). unit = nombre de lo que se califica.
    Habilidad medible (mode="measure"): se ve la medición (103) con el color de su nivel; el valor guardado es la calificación.
--}}
@props(['kind', 'id', 'student', 'unit', 'value' => null, 'term' => null, 'mode' => null, 'measure' => null])
@php
    $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $v = $fmt($value);
    $m = $fmt($measure);
    $mode ??= in_array($kind, ['criterion', 'skill'], true) ? 'levels' : 'score';
    $level = \App\Support\Level::of($value);
    $shown = $mode === 'measure' ? ($m !== '' ? $m : $v) : $v;
@endphp
<button type="button" {{ $attributes->merge(['class' => 'score']) }}
        data-score-cell data-kind="{{ $kind }}" data-id="{{ $id }}" @if ($term) data-term="{{ $term }}" @endif
        data-mode="{{ $mode }}" @if ($mode === 'measure') data-measure="{{ $m }}" @endif
        data-student="{{ $student->id }}" data-student-name="{{ $student->name }}" data-unit-name="{{ $unit }}"
        data-value="{{ $v }}" @if ($v === '') data-empty @else data-level="{{ $level }}" @endif
        @if ($mode === 'measure' && $v !== '') title="Calificación {{ $v }}" @endif
        aria-label="{{ $student->name }}, {{ $unit }}: {{ $v === '' ? 'sin calificar' : ($mode === 'measure' ? $m.' → '.$v : $v) }}">{{ $shown === '' ? '—' : $shown }}</button>
