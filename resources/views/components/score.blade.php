{{-- Botón de calificación: tap → teclado. Vacío = pendiente (no es 0). --}}
@props(['student', 'criterion', 'value' => null])
@php($v = $value === null ? '' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'))
<button type="button" {{ $attributes->merge(['class' => 'score']) }}
        data-score-cell
        data-student="{{ $student->id }}" data-student-name="{{ $student->name }}"
        data-criterion="{{ $criterion->id }}" data-criterion-name="{{ $criterion->name }}"
        data-value="{{ $v }}" @if ($v === '') data-empty @endif
        aria-label="{{ $student->name }}, {{ $criterion->name }}: {{ $v === '' ? 'sin calificar' : $v }}">{{ $v === '' ? '—' : $v }}</button>
