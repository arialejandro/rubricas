{{--
    Celda de calificación: tap → teclado (resources/js/keypad.js). Vacío = pendiente (no es 0).
    kind: criterion (nivel 6–10) | aspect | subject (0–10). unit = nombre de lo que se califica.
--}}
@props(['kind', 'id', 'student', 'unit', 'value' => null, 'term' => null])
@php
    $v = $value === null ? '' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $level = \App\Support\Level::of($value);
@endphp
<button type="button" {{ $attributes->merge(['class' => 'score']) }}
        data-score-cell data-kind="{{ $kind }}" data-id="{{ $id }}" @if ($term) data-term="{{ $term }}" @endif
        data-mode="{{ $kind === 'criterion' ? 'levels' : 'score' }}"
        data-student="{{ $student->id }}" data-student-name="{{ $student->name }}" data-unit-name="{{ $unit }}"
        data-value="{{ $v }}" @if ($v === '') data-empty @else data-level="{{ $level }}" @endif
        aria-label="{{ $student->name }}, {{ $unit }}: {{ $v === '' ? 'sin calificar' : $v }}">{{ $v === '' ? '—' : $v }}</button>
