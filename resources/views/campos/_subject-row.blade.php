<div class="flex items-center gap-2" data-repeater-row>
    <input type="hidden" name="subjects[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <input class="input flex-1" name="subjects[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Materia (ej. Inglés)" aria-label="Nombre de la materia">
    <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar materia" data-repeater-remove><x-icon name="x" /></button>
</div>
