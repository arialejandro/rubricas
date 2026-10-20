@use('App\Support\Level')
@php
    $num = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $measured = filled($row['measure_unit'] ?? null);
    $lower = ! empty($row['lower_is_better']);
@endphp
<div class="rounded-2xl border border-line p-3" data-repeater-row data-skill-row>
    <input type="hidden" name="skills[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex items-center gap-2">
        <input class="input flex-1 font-semibold" name="skills[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Habilidad (ej. Velocidad lectora)" aria-label="Habilidad">
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar habilidad"
                title="{{ ! empty($row['graded']) ? 'Tiene '.$row['graded'].' calificaciones' : 'Quitar' }}" data-repeater-remove><x-icon name="x" /></button>
    </div>

    {{-- Cómo se califica --}}
    <div class="mt-2 flex gap-2" role="radiogroup" aria-label="Cómo se califica">
        <label class="flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-line px-3 text-sm font-semibold text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary">
            <input type="radio" class="sr-only" name="skills[{{ $i }}][mode]" value="level" data-skill-mode @checked(! $measured)> Elijo el nivel
        </label>
        <label class="flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-line px-3 text-sm font-semibold text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary">
            <input type="radio" class="sr-only" name="skills[{{ $i }}][mode]" value="measure" data-skill-mode @checked($measured)> Mido y se calcula
        </label>
    </div>

    {{-- Medición: unidad + rangos --}}
    <div class="mt-3 space-y-3 rounded-xl bg-surface-2 p-3" data-skill-measure @unless ($measured) hidden @endunless>
        <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
            <div>
                <label class="label">Qué se mide</label>
                <input class="input" name="skills[{{ $i }}][measure_unit]" value="{{ $row['measure_unit'] ?? '' }}" placeholder="palabras por minuto, aciertos, errores…" list="measure-units" data-skill-unit>
            </div>
            <label class="flex min-h-12 items-center gap-2 text-sm font-medium">
                <input type="checkbox" class="size-5" name="skills[{{ $i }}][lower_is_better]" value="1" @checked($lower) data-skill-lower> Menos es mejor
            </label>
        </div>
        <div>
            <p class="label">Rangos <span class="font-normal text-ink-muted" data-skill-cut-hint>{{ $lower ? '(hasta cuánto vale cada calificación)' : '(desde cuánto vale cada calificación)' }}</span></p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach (['min_10' => [10, 'logrado'], 'min_9' => [9, 'satisfactorio'], 'min_8' => [8, 'proceso'], 'min_7' => [7, 'proceso']] as $col => [$score, $lvl])
                    <label class="block rounded-xl p-2" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                        <span class="mb-1 block text-xs font-bold" style="color: var(--lvl)"><span data-skill-cut-word>{{ $lower ? 'Hasta' : 'Desde' }}</span> = {{ $score }}</span>
                        <input class="input bg-surface text-center font-bold tabular-nums" name="skills[{{ $i }}][{{ $col }}]" value="{{ $num($row[$col] ?? null) }}" inputmode="decimal">
                    </label>
                @endforeach
            </div>
            <p class="hint">Lo que quede fuera de estos rangos vale 6. Ej. velocidad lectora: desde 115 = 10, desde 100 = 9, desde 92 = 8, desde 85 = 7, menos de 85 = 6.</p>
        </div>
    </div>

    <details class="group mt-2">
        <summary class="flex min-h-10 list-none items-center gap-2 text-sm font-semibold text-primary">
            <x-icon name="chevron-right" class="size-4 transition group-open:rotate-90" /> Nombres y descripción de cada nivel <span class="font-normal text-ink-muted">(opcional)</span>
        </summary>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach (Level::LEVELS as $lvl => $l)
                <div class="rounded-xl p-2" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                    <span class="mb-1 block text-xs font-bold" style="color: var(--lvl)">{{ implode('/', $l['scores']) }}</span>
                    <input class="input mb-1 min-h-10 bg-surface py-1.5 text-sm font-semibold" name="skills[{{ $i }}][level_labels][{{ $lvl }}]" value="{{ $row['level_labels'][$lvl] ?? '' }}" placeholder="{{ $l['label'] }}" aria-label="Nombre del nivel {{ $l['label'] }}">
                    <textarea class="input min-h-14 bg-surface text-sm" name="skills[{{ $i }}][{{ Level::column($lvl) }}]" rows="2" placeholder="Qué significa (opcional)">{{ $row[Level::column($lvl)] ?? '' }}</textarea>
                </div>
            @endforeach
        </div>
    </details>
</div>
