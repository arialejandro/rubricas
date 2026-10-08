{{--
    Teclado de calificaciones (ver resources/js/keypad.js). No es modal ni mueve la página:
    en iPad/escritorio aparece como pop-up junto a la casilla tocada y la sigue al avanzar;
    en teléfono es una hoja inferior.
--}}
<div id="keypad" hidden class="fixed inset-x-0 bottom-0 z-40 md:right-auto md:bottom-auto md:w-[320px]"
     role="dialog" aria-labelledby="keypad-student" aria-describedby="keypad-criterion">
    <div class="rounded-t-3xl border border-line bg-surface p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-[0_-12px_40px_rgb(30_10_60/0.22)] ring-1 ring-primary/10 md:rounded-3xl md:p-3.5 md:shadow-[0_20px_50px_rgb(30_10_60/0.28)]">
        <div class="mb-3 flex items-start gap-2">
            <button type="button" class="btn btn-ghost btn-icon shrink-0" data-pad-prev aria-label="Alumno anterior"><x-icon name="chevron-left" /></button>
            <div class="min-w-0 flex-1 text-center">
                <p id="keypad-student" class="truncate text-lg font-bold leading-tight" data-pad-student></p>
                <p class="truncate text-sm text-ink-muted"><span id="keypad-criterion" data-pad-criterion></span> · <span data-pad-current></span></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon shrink-0" data-pad-close aria-label="Cerrar teclado"><x-icon name="x" /></button>
        </div>

        <div class="grid grid-cols-3 gap-2" data-pad-grid>
            @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                <button type="button" class="key aria-pressed:border-primary aria-pressed:bg-primary-soft" data-key="{{ $n }}">{{ $n }}</button>
            @endforeach
            <button type="button" class="key aria-pressed:border-primary aria-pressed:bg-primary-soft" data-key="0">0</button>
            <button type="button" class="key border-mint bg-mint text-[#10302a] hover:bg-mint/85 aria-pressed:ring-4 aria-pressed:ring-primary-soft" data-key="10">10</button>
            <button type="button" class="key text-base text-ink-muted" data-pad-decimal aria-label="Escribir con decimales">0,0</button>
            <button type="button" class="key col-span-1 text-base text-danger" data-pad-clear aria-label="Borrar calificación (queda pendiente)">
                <x-icon name="backspace" class="size-6" />
            </button>
            <button type="button" class="key col-span-2 gap-1 text-base text-ink-muted" data-pad-skip>
                <span class="flex items-center gap-1">Omitir <x-icon name="chevron-right" class="size-5" /></span>
            </button>
        </div>

        <form class="space-y-2" data-pad-decimal-form hidden>
            <label class="label" for="keypad-decimal">Calificación con decimales</label>
            <div class="flex gap-2">
                <input id="keypad-decimal" class="input num text-center text-2xl font-bold" inputmode="decimal" autocomplete="off" placeholder="8,5" enterkeyhint="done">
                <button class="btn btn-primary px-6">Guardar</button>
            </div>
            <p class="hint">Del 0 al 10, máximo dos decimales. Usa coma o punto.</p>
        </form>
    </div>
</div>
