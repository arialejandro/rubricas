@use('App\Support\Level')
<div class="rounded-xl border border-line bg-surface p-2" data-repeater-row>
    <input type="hidden" name="skills[{{ $i }}][criteria][{{ $j }}][id]" value="{{ $c['id'] ?? '' }}">
    <div class="flex items-start gap-2">
        <textarea class="input min-h-11 flex-1 font-semibold" rows="1" name="skills[{{ $i }}][criteria][{{ $j }}][description]" placeholder="Criterio (ej. Resuelve multiplicaciones de 2 cifras)" aria-label="Criterio">{{ $c['description'] ?? '' }}</textarea>
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar criterio" data-repeater-remove><x-icon name="x" /></button>
    </div>
    <details class="group mt-1">
        <summary class="flex min-h-9 list-none items-center gap-2 text-xs font-semibold text-primary">
            <x-icon name="chevron-right" class="size-3.5 transition group-open:rotate-90" /> Qué significa cada nivel (opcional)
        </summary>
        <div class="mt-1 grid gap-2 sm:grid-cols-2">
            @foreach (Level::LEVELS as $lvl => $l)
                <label class="block rounded-lg p-1.5" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                    <span class="mb-0.5 block text-[11px] font-bold" style="color: var(--lvl)">{{ $l['label'] }} · {{ implode('/', $l['scores']) }}</span>
                    <textarea class="input min-h-12 bg-surface text-sm" rows="2" name="skills[{{ $i }}][criteria][{{ $j }}][{{ Level::column($lvl) }}]">{{ $c[Level::column($lvl)] ?? '' }}</textarea>
                </label>
            @endforeach
        </div>
    </details>
</div>
