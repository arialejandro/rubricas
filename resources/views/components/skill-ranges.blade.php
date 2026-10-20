{{-- Equivalencias de una habilidad medible: a qué calificación corresponde cada rango de la medición. --}}
@props(['skill', 'title' => true])
<div {{ $attributes->merge(['class' => 'card p-3 sm:p-4']) }}>
    @if ($title)
        <p class="mb-2 text-sm font-semibold">{{ $skill->name }} <span class="font-normal text-ink-muted">· {{ $skill->measure_unit }}{{ $skill->lower_is_better ? ' (menos es mejor)' : '' }}</span></p>
    @endif
    <div class="grid grid-cols-5 gap-1.5 sm:gap-2">
        @foreach ($skill->scoreBands() as $band)
            <div class="rounded-xl px-2 py-2 text-center" data-level="{{ $band['level'] }}" style="background: var(--lvl-soft)">
                <div class="text-2xl font-bold tabular-nums leading-none" style="color: var(--lvl)">{{ $band['score'] }}</div>
                <div class="mt-1 text-[11px] font-semibold leading-tight sm:text-xs" style="color: var(--lvl)">{{ $band['label'] }}</div>
                <div class="mt-0.5 text-[11px] leading-tight text-ink sm:text-xs">{{ $band['range'] }}</div>
            </div>
        @endforeach
    </div>
</div>
