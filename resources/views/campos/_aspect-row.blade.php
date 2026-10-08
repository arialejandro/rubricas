<div class="rounded-2xl border border-line p-3" data-repeater-row>
    <input type="hidden" name="aspects[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex items-center gap-2">
        <input class="input flex-1" name="aspects[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Aspecto (ej. Examen)" aria-label="Nombre del aspecto">
        <div class="relative w-24 shrink-0">
            <input class="input pr-8 text-right font-semibold tabular-nums" name="aspects[{{ $i }}][weight]" value="{{ $row['weight'] ?? '' }}" inputmode="decimal" placeholder="0" aria-label="Porcentaje" data-weight>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-muted">%</span>
        </div>
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar aspecto"
                title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" data-repeater-remove><x-icon name="x" /></button>
    </div>
    <div class="mt-2 flex gap-2" role="radiogroup" aria-label="Cómo se califica">
        @foreach (\App\Models\TermAspect::TYPES as $value => $label)
            <label class="flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-line px-3 text-sm font-semibold text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary">
                <input type="radio" class="sr-only" name="aspects[{{ $i }}][type]" value="{{ $value }}" @checked(($row['type'] ?? 'direct') === $value)>
                <x-icon :name="$value === 'projects' ? 'folder' : 'pencil'" class="size-4" /> {{ $label }}
            </label>
        @endforeach
    </div>
</div>
