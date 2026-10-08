<div class="flex items-center gap-2" data-criterion-row>
    <input type="hidden" name="criteria[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <input class="input flex-1" name="criteria[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Aspecto (ej. Creatividad)" aria-label="Nombre del aspecto">
    <div class="relative w-24 shrink-0">
        <input class="input pr-7 text-right" name="criteria[{{ $i }}][weight]" value="{{ $row['weight'] ?? '' }}" inputmode="decimal" placeholder="0" aria-label="Porcentaje" data-weight>
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">%</span>
    </div>
    <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600"
            title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" aria-label="Quitar aspecto" data-remove-criterion>✕</button>
</div>
