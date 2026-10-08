{{-- Leyenda de niveles de logro (verde mejor → rojo peor). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-xs']) }}>
    <span class="font-semibold text-ink-muted">Niveles:</span>
    @foreach (\App\Support\Level::LEVELS as $key => $l)
        <span class="lvl-chip" data-level="{{ $key }}">{{ $l['label'] }} · {{ implode('/', $l['scores']) }}</span>
    @endforeach
</div>
