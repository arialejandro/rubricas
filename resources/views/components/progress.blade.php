{{-- Barra de avance. `bar` = clave que keypad.js actualiza en vivo (data-bar). --}}
@props(['value' => 0, 'bar' => null])
<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-surface-2']) }} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}">
    <div @if ($bar) data-bar="{{ $bar }}" @endif
         class="h-full rounded-full bg-linear-to-r from-primary to-mint transition-[width] duration-300"
         style="width: {{ $value }}%"></div>
</div>
