<div class="flex items-center gap-2" data-criterion-row>
    <input type="hidden" name="criteria[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <input class="input flex-1" name="criteria[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Aspecto (ej. Creatividad)" aria-label="Nombre del aspecto">
    <div class="relative w-24 shrink-0">
        <input class="input num pr-8 text-right font-semibold" name="criteria[{{ $i }}][weight]" value="{{ $row['weight'] ?? '' }}" inputmode="decimal" placeholder="0" aria-label="Porcentaje" data-weight>
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-muted">%</span>
    </div>
    <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger"
            title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" aria-label="Quitar aspecto" data-remove-criterion><x-icon name="x" /></button>
</div>
