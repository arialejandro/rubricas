{{-- Fecha / nombre de la aplicación y eliminarla. Requiere $group, $skill, $application. --}}
<details class="card group mb-4">
    <summary class="flex min-h-12 list-none items-center gap-2 px-4 text-sm font-semibold">
        <x-icon name="calendar" class="size-4" /> Fecha y nombre de esta aplicación
        <x-icon name="chevron-right" class="ml-auto size-4 transition group-open:rotate-90" />
    </summary>
    <div class="flex flex-wrap items-end gap-3 border-t border-line p-4">
        <form method="POST" action="{{ route('skills.applications.update', [$group, $skill, $application]) }}" class="flex flex-1 flex-wrap items-end gap-2">
            @csrf @method('PUT')
            <label class="block"><span class="label">Fecha</span>
                <input type="date" name="applied_on" value="{{ $application->applied_on?->format('Y-m-d') }}" class="input min-h-11 py-2">
            </label>
            <label class="block flex-1"><span class="label">Nombre (opcional)</span>
                <input name="label" value="{{ $application->label }}" class="input min-h-11 py-2" placeholder="Ej. Diagnóstico, Cierre…" maxlength="80">
            </label>
            <button class="btn btn-ghost">Guardar</button>
        </form>
        <form method="POST" action="{{ route('skills.applications.destroy', [$group, $skill, $application]) }}" data-confirm="¿Eliminar esta aplicación y todas sus calificaciones?">
            @csrf @method('DELETE')
            <button class="btn btn-danger"><x-icon name="trash" class="size-4" /> Eliminar</button>
        </form>
    </div>
</details>
