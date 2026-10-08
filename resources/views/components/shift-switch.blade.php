{{--
    Selector de turno como cielo: matutino = amanecer azul claro con el sol saliendo;
    vespertino = atardecer naranja con medio sol ocultándose. Animación en resources/js/shift.js
    y keyframes en app.css (.shift-*). El turno que aún no tiene grupo lleva a crearlo.
--}}
@props(['groups', 'current'])
<div class="flex gap-1 rounded-2xl border border-line bg-surface-2 p-1" role="group" aria-label="Turno">
    @foreach (\App\Models\Group::SHIFTS as $shift => $label)
        @php($g = $groups->get($shift))
        @php($on = $current && $current->shift === $shift)
        <a href="{{ $g ? route('grupos.show', $g) : route('grupos.create', ['shift' => $shift]) }}"
           @class(['shift-seg', 'shift-'.$shift, 'is-active' => $on])
           data-shift-link @if ($on) aria-current="true" @endif
           title="{{ $g ? $label.' · '.$g->label() : 'Agregar grupo '.strtolower($label) }}">
            <span class="shift-sky" aria-hidden="true"></span>
            <svg class="shift-scene" viewBox="0 0 32 32" aria-hidden="true">
                <defs>
                    <clipPath id="horizon-{{ $shift }}"><rect x="0" y="0" width="32" height="{{ $shift === 'matutino' ? 27 : 21 }}" /></clipPath>
                </defs>
                <g clip-path="url(#horizon-{{ $shift }})">
                    <g class="shift-sun">
                        @if ($shift === 'matutino')
                            <g class="shift-rays">
                                @foreach (range(0, 315, 45) as $deg)
                                    <rect x="15" y="3" width="2" height="4" rx="1" transform="rotate({{ $deg }} 16 14)" />
                                @endforeach
                            </g>
                            <circle cx="16" cy="14" r="5.5" />
                        @else
                            <circle cx="16" cy="21" r="7" />
                        @endif
                    </g>
                </g>
                @if ($shift === 'vespertino')
                    <path class="shift-horizon" d="M3 21h26" stroke-width="2" stroke-linecap="round" />
                    <path class="shift-horizon" d="M8 25h16" stroke-width="2" stroke-linecap="round" opacity=".6" />
                @endif
            </svg>
            <span class="relative hidden sm:inline">{{ $label }}</span>
            @unless ($g) <x-icon name="plus" class="relative size-3.5" /> @endunless
        </a>
    @endforeach
</div>
