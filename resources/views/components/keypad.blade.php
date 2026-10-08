{{--
    Teclado de calificaciones (ver resources/js/keypad.js). No es modal ni mueve la página:
    en iPad/escritorio aparece como pop-up junto a la casilla tocada y la sigue al avanzar;
    en teléfono es una hoja inferior.
    Dos modos según la casilla: "score" (0–10, decimales a mano) y "levels" (criterios de
    proyecto: Logrado 10 · Satisfactorio 9 · En proceso 8/7 · Requiere apoyo 6, con el
    descriptor de la rúbrica debajo de cada nivel).
--}}
<div id="keypad" hidden class="fixed inset-x-0 bottom-0 z-40 md:right-auto md:bottom-auto md:w-[340px]"
     role="dialog" aria-labelledby="keypad-student" aria-describedby="keypad-unit">
    <div class="max-h-[85dvh] overflow-y-auto rounded-t-3xl border border-line bg-surface p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-[0_-12px_40px_rgb(30_10_60/0.22)] ring-1 ring-primary/10 md:rounded-3xl md:p-3.5 md:shadow-[0_20px_50px_rgb(30_10_60/0.28)]">
        <div class="mb-3 flex items-start gap-2">
            <button type="button" class="btn btn-ghost btn-icon shrink-0" data-pad-prev aria-label="Alumno anterior"><x-icon name="chevron-left" /></button>
            <div class="min-w-0 flex-1 text-center">
                <p id="keypad-student" class="truncate text-lg font-bold leading-tight" data-pad-student></p>
                <p class="truncate text-sm text-ink-muted"><span id="keypad-unit" data-pad-unit></span> · <span data-pad-current></span></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon shrink-0" data-pad-close aria-label="Cerrar teclado"><x-icon name="x" /></button>
        </div>

        {{-- 0–10 --}}
        <div class="grid grid-cols-3 gap-2" data-pad-grid>
            @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                <button type="button" class="key aria-pressed:border-primary aria-pressed:bg-primary-soft" data-key="{{ $n }}">{{ $n }}</button>
            @endforeach
            <button type="button" class="key aria-pressed:border-primary aria-pressed:bg-primary-soft" data-key="0">0</button>
            <button type="button" class="key border-mint bg-mint text-[#10302a] hover:bg-mint/85 aria-pressed:ring-4 aria-pressed:ring-primary-soft" data-key="10">10</button>
            <button type="button" class="key text-base text-ink-muted" data-pad-decimal aria-label="Escribir con decimales">0,0</button>
        </div>

        {{-- Niveles de logro --}}
        <div class="grid grid-cols-2 gap-2" data-pad-levels hidden>
            @foreach ([[10, 'logrado', 'col-span-2'], [9, 'satisfactorio', 'col-span-2'], [8, 'proceso', ''], [7, 'proceso', ''], [6, 'apoyo', 'col-span-2']] as [$n, $lvl, $span])
                <button type="button" data-key="{{ $n }}" data-level="{{ $lvl }}"
                        class="{{ $span }} flex min-h-14 flex-col items-start justify-center rounded-2xl border-2 px-3 py-2 text-left transition active:scale-[.97] aria-pressed:ring-4 aria-pressed:ring-primary-soft"
                        style="border-color: color-mix(in srgb, var(--lvl) 45%, transparent); background: var(--lvl-soft); color: var(--lvl)">
                    <span class="flex w-full items-baseline justify-between gap-2 font-bold">
                        <span>{{ \App\Support\Level::label($lvl) }}</span><span class="tabular-nums text-xl">{{ $n }}</span>
                    </span>
                    <span class="line-clamp-2 text-xs font-medium text-ink-muted" data-pad-descriptor="{{ $lvl }}"></span>
                </button>
            @endforeach
        </div>

        <form class="space-y-2" data-pad-decimal-form hidden>
            <label class="label" for="keypad-decimal">Calificación con decimales</label>
            <div class="flex gap-2">
                <input id="keypad-decimal" class="input text-center text-2xl font-bold tabular-nums" inputmode="decimal" autocomplete="off" placeholder="8.5" enterkeyhint="done">
                <button class="btn btn-primary px-6">Guardar</button>
            </div>
            <p class="hint">Del 0 al 10, máximo dos decimales.</p>
        </form>

        <div class="mt-2 grid grid-cols-3 gap-2">
            <button type="button" class="key min-h-12 text-base text-danger" data-pad-clear aria-label="Borrar calificación (queda pendiente)">
                <x-icon name="backspace" class="size-6" />
            </button>
            <button type="button" class="key col-span-2 min-h-12 text-base text-ink-muted" data-pad-skip>
                <span class="flex items-center gap-1">Omitir <x-icon name="chevron-right" class="size-5" /></span>
            </button>
        </div>
    </div>
</div>
