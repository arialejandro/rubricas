@use('App\Support\Level')
<div class="rounded-2xl border border-line p-3" data-repeater-row>
    <input type="hidden" name="skills[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex items-center gap-2">
        <input class="input flex-1 font-semibold" name="skills[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Habilidad (ej. Velocidad lectora)" aria-label="Habilidad">
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar habilidad"
                title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" data-repeater-remove><x-icon name="x" /></button>
    </div>
    <details class="group mt-2">
        <summary class="flex min-h-10 list-none items-center gap-2 text-sm font-semibold text-primary">
            <x-icon name="chevron-right" class="size-4 transition group-open:rotate-90" /> Qué significa cada nivel <span class="font-normal text-ink-muted">(opcional)</span>
        </summary>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach (Level::LEVELS as $lvl => $l)
                <label class="block rounded-xl p-2" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                    <span class="mb-1 block text-xs font-bold" style="color: var(--lvl)">{{ $l['label'] }} · {{ implode('/', $l['scores']) }}</span>
                    <textarea class="input min-h-14 bg-surface text-sm" name="skills[{{ $i }}][{{ Level::column($lvl) }}]" rows="2" placeholder="Ej. lee más de 100 palabras por minuto">{{ $row[Level::column($lvl)] ?? '' }}</textarea>
                </label>
            @endforeach
        </div>
    </details>
</div>
