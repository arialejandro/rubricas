@use('App\Support\Level')
<div class="space-y-2 rounded-2xl border border-line p-3" data-repeater-row>
    <input type="hidden" name="criteria[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex items-start gap-2">
        <textarea class="input min-h-12 flex-1 font-semibold" name="criteria[{{ $i }}][description]" rows="1" placeholder="Criterio a observar (ej. Informa un hecho real)" aria-label="Criterio">{{ $row['description'] ?? '' }}</textarea>
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar criterio"
                title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" data-repeater-remove><x-icon name="x" /></button>
    </div>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach (Level::LEVELS as $lvl => $l)
            <label class="block rounded-xl p-2" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                <span class="mb-1 block text-xs font-bold" style="color: var(--lvl)">{{ $l['label'] }} · {{ implode('/', $l['scores']) }}</span>
                <textarea class="input min-h-16 bg-surface text-sm" name="criteria[{{ $i }}][{{ Level::column($lvl) }}]" rows="2" placeholder="Qué significa {{ strtolower($l['label']) }} (opcional)">{{ $row[Level::column($lvl)] ?? '' }}</textarea>
            </label>
        @endforeach
    </div>
</div>
