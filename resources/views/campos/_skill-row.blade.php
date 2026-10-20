@use('App\Support\Level')
@use('App\Models\Skill')
@php
    $num = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $grading = $row['grading'] ?? 'level';
    $lower = ! empty($row['lower_is_better']);
    $criteria = $row['criteria'] ?? [];
@endphp
<div class="rounded-2xl border border-line p-3" data-repeater-row data-skill-row>
    <input type="hidden" name="skills[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex items-center gap-2">
        <input class="input flex-1 font-semibold" name="skills[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Habilidad (ej. Multiplicación)" aria-label="Habilidad">
        <button type="button" class="btn btn-ghost btn-icon shrink-0 text-ink-muted hover:text-danger" aria-label="Quitar habilidad"
                title="{{ ! empty($row['graded']) ? 'Tiene calificaciones' : 'Quitar' }}" data-repeater-remove><x-icon name="x" /></button>
    </div>

    {{-- Cómo se califica --}}
    <div class="mt-2 grid grid-cols-3 gap-2" role="radiogroup" aria-label="Cómo se califica">
        @foreach (Skill::GRADINGS as $value => $label)
            <label class="flex min-h-11 items-center justify-center rounded-xl border border-line px-2 text-center text-sm font-semibold leading-tight text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary">
                <input type="radio" class="sr-only" name="skills[{{ $i }}][grading]" value="{{ $value }}" data-skill-mode @checked($grading === $value)> {{ $label }}
            </label>
        @endforeach
    </div>

    {{-- Mido y se calcula: unidad + dónde empieza cada nivel --}}
    <div class="mt-3 space-y-3 rounded-xl bg-surface-2 p-3" data-skill-panel="measure" @unless ($grading === 'measure') hidden @endunless>
        <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
            <label class="block"><span class="label">Qué se mide</span>
                <input class="input" name="skills[{{ $i }}][measure_unit]" value="{{ $row['measure_unit'] ?? '' }}" placeholder="palabras por minuto, aciertos, errores…" list="measure-units">
            </label>
            <label class="flex min-h-12 items-center gap-2 text-sm font-medium">
                <input type="checkbox" class="size-5" name="skills[{{ $i }}][lower_is_better]" value="1" @checked($lower) data-skill-lower> Menos es mejor
            </label>
        </div>
        <div>
            <p class="label">Dónde <span data-skill-cut-word>{{ $lower ? 'termina' : 'empieza' }}</span> cada nivel</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach (['min_7' => ['proceso', 'En proceso (7–8)'], 'min_9' => ['satisfactorio', 'Satisfactorio (9)'], 'min_10' => ['logrado', 'Logrado (10)']] as $col => [$lvl, $text])
                    <label class="block rounded-xl p-2" data-level="{{ $lvl }}" style="background: var(--lvl-soft)">
                        <span class="mb-1 block text-xs font-bold leading-tight" style="color: var(--lvl)">{{ $text }}</span>
                        <input class="input bg-surface text-center font-bold tabular-nums" name="skills[{{ $i }}][{{ $col }}]" value="{{ $num($row[$col] ?? null) }}" inputmode="decimal">
                    </label>
                @endforeach
            </div>
            <p class="hint">Fuera de esos rangos es Requiere apoyo (6). En proceso se divide a la mitad entre su valor más bajo y el más alto: la primera mitad es 7 y la segunda 8. Ej. En proceso 85, Satisfactorio 100 → 85–91 = 7, 92–99 = 8.</p>
        </div>
    </div>

    {{-- Por criterios: cada criterio con su descriptor por nivel --}}
    <div class="mt-3 space-y-3 rounded-xl bg-surface-2 p-3" data-skill-panel="criteria" @unless ($grading === 'criteria') hidden @endunless>
        <p class="text-sm text-ink-muted">Cada criterio se califica por nivel; la calificación de la habilidad es su promedio, redondeado al nivel.</p>
        <section class="space-y-2" data-repeater data-max="10">
            <div class="space-y-2" data-repeater-list>
                @foreach ($criteria as $j => $c)
                    @include('campos._skill-criterion-row', ['i' => $i, 'j' => $j, 'c' => $c])
                @endforeach
            </div>
            <template>@include('campos._skill-criterion-row', ['i' => $i, 'j' => '__CIDX__', 'c' => []])</template>
            <button type="button" class="btn btn-ghost min-h-10" data-repeater-add data-placeholder="__CIDX__"><x-icon name="plus" class="size-4" /> Agregar criterio</button>
        </section>
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
