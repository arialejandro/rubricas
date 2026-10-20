{{-- Agregar a la lista actual o reemplazarla (ver data-replace-guard en resources/js/app.js). --}}
<div class="flex rounded-xl border border-line bg-surface-2 p-1 text-sm font-semibold" role="radiogroup" aria-label="Qué hacer con la lista actual">
    <label class="flex min-h-10 items-center rounded-lg px-3 text-ink-muted has-checked:bg-surface has-checked:text-ink has-checked:shadow-sm">
        <input type="radio" name="replace" value="0" class="sr-only" checked> Agregar
    </label>
    <label class="flex min-h-10 items-center rounded-lg px-3 text-ink-muted has-checked:bg-danger-soft has-checked:text-danger">
        <input type="radio" name="replace" value="1" class="sr-only"> Reemplazar lista
    </label>
</div>
