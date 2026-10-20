<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Skill;
use App\Models\SkillCriterion;
use App\Models\TermAspect;
use App\Support\Campos;
use App\Support\Level;
use App\Support\TermBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampoController extends Controller
{
    public function index(Group $group): View
    {
        $book = TermBook::for($group);

        return view('campos.index', compact('group', 'book'));
    }

    public function show(Group $group, string $campo): View
    {
        $book = TermBook::for($group);

        return view('campos.show', compact('group', 'book', 'campo'));
    }

    /** Estrategia de evaluación del campo en el trimestre: aspectos con % + materias adicionales. */
    public function edit(Group $group, string $campo): View
    {
        $term = $group->term();
        $aspects = $group->termAspects()->where('term', $term)->where('campo', $campo)->withCount('scores')->get();
        $subjects = $group->subjects()->where('campo', $campo)->withCount('scores')->get();
        $skills = Campos::hasSkills($campo) ? $group->skills()->where('campo', $campo)->with('criteria')->withCount('scores')->get() : collect();
        $canCopy = $aspects->isEmpty() && $term > 1
            && $group->termAspects()->where('term', $term - 1)->where('campo', $campo)->exists();

        return view('campos.edit', compact('group', 'campo', 'term', 'aspects', 'subjects', 'skills', 'canCopy'));
    }

    public function update(Request $request, Group $group, string $campo): RedirectResponse
    {
        $term = $group->term();

        // Coma decimal del iPad ("12,5") y renglones vacíos fuera.
        $aspects = collect($request->input('aspects', []))
            ->filter(fn ($a) => trim($a['name'] ?? '') !== '' || trim($a['weight'] ?? '') !== '')
            ->map(fn ($a) => [...$a, 'weight' => str_replace(',', '.', trim($a['weight'] ?? ''))])
            ->values()->all();
        $subjects = collect($request->input('subjects', []))->filter(fn ($s) => trim($s['name'] ?? '') !== '')->values()->all();
        $skills = Campos::hasSkills($campo)
            ? collect($request->input('skills', []))->filter(fn ($s) => trim($s['name'] ?? '') !== '')->values()->all()
            : [];
        $request->merge(['aspects' => $aspects, 'subjects' => $subjects, 'skills' => $skills]);

        $types = Campos::hasSkills($campo) ? array_keys(TermAspect::TYPES) : ['projects', 'direct'];
        $levelRules = collect(array_keys(Level::LEVELS))->flatMap(fn ($l) => [
            'skills.*.'.Level::column($l) => ['nullable', 'string', 'max:1000'],
            "skills.*.level_labels.{$l}" => ['nullable', 'string', 'max:40'],
            'skills.*.criteria.*.'.Level::column($l) => ['nullable', 'string', 'max:1000'],
        ])->merge(collect(['min_7', 'min_9', 'min_10'])->mapWithKeys(fn ($c) => ["skills.*.{$c}" => ['nullable', 'numeric', 'min:0', 'max:99999']]))
            ->merge([
                'skills.*.grading' => ['required', Rule::in(array_keys(Skill::GRADINGS))],
                'skills.*.measure_unit' => ['nullable', 'string', 'max:40'],
                'skills.*.lower_is_better' => ['nullable', 'boolean'],
                'skills.*.criteria' => ['nullable', 'array', 'max:10'],
                'skills.*.criteria.*.id' => ['nullable', 'integer'],
                'skills.*.criteria.*.description' => ['required', 'string', 'max:255'],
            ])
            ->all();
        // Coma decimal en los rangos y criterios vacíos fuera.
        $request->merge(['skills' => collect($request->input('skills', []))->map(function ($k) {
            foreach (['min_7', 'min_9', 'min_10'] as $c) {
                if (isset($k[$c])) {
                    $k[$c] = str_replace(',', '.', trim((string) $k[$c]));
                }
            }
            $k['criteria'] = collect($k['criteria'] ?? [])->filter(fn ($c) => trim($c['description'] ?? '') !== '')->values()->all();

            return $k;
        })->all()]);

        $data = $request->validate([
            'aspects' => ['required', 'array', 'min:1', 'max:'.Campos::MAX_ASPECTS],
            'aspects.*.id' => ['nullable', 'integer'],
            'aspects.*.name' => ['required', 'string', 'max:120'],
            'aspects.*.type' => ['required', Rule::in($types)],
            'aspects.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'subjects' => ['array', 'max:4'],
            'subjects.*.id' => ['nullable', 'integer'],
            'subjects.*.name' => ['required', 'string', 'max:80'],
            'skills' => ['array', 'max:8'],
            'skills.*.id' => ['nullable', 'integer'],
            'skills.*.name' => ['required', 'string', 'max:120'],
            ...$levelRules,
        ], [
            'aspects.required' => 'Agrega al menos un aspecto.',
            'aspects.max' => 'Máximo '.Campos::MAX_ASPECTS.' aspectos por campo.',
            'aspects.*.weight.required' => 'Cada aspecto necesita su porcentaje.',
            'aspects.*.weight.gt' => 'El porcentaje debe ser mayor que 0.',
        ]);

        $sum = round(array_sum(array_column($data['aspects'], 'weight')), 2);
        if (abs($sum - 100) >= 0.01) {
            throw ValidationException::withMessages(['aspects' => "Los porcentajes suman {$sum}%; deben sumar exactamente 100%."]);
        }
        if (collect($data['aspects'])->where('type', 'projects')->count() > 1) {
            throw ValidationException::withMessages(['aspects' => 'Solo un aspecto puede tomar su calificación de los proyectos.']);
        }
        if (collect($data['aspects'])->where('type', 'skills')->count() > 1) {
            throw ValidationException::withMessages(['aspects' => 'Solo un aspecto puede tomar su calificación de las habilidades prioritarias.']);
        }
        $data['skills'] = collect($data['skills'] ?? [])->map(fn ($k) => $this->normalizeSkill($k))->all();

        $aspectQuery = $group->termAspects()->where('term', $term)->where('campo', $campo);
        $removedAspects = (clone $aspectQuery)->whereNotIn('id', collect($data['aspects'])->pluck('id')->filter())->withCount('scores')->get();
        $subjectQuery = $group->subjects()->where('campo', $campo);
        $removedSubjects = (clone $subjectQuery)->whereNotIn('id', collect($data['subjects'] ?? [])->pluck('id')->filter())->withCount('scores')->get();
        $skillQuery = $group->skills()->where('campo', $campo);
        $removedSkills = Campos::hasSkills($campo)
            ? (clone $skillQuery)->whereNotIn('id', collect($data['skills'] ?? [])->pluck('id')->filter())->withCount('applications')->get()
            : collect();

        // Criterios de habilidad que se quitan y ya tienen calificaciones.
        $removedCriteria = collect($data['skills'] ?? [])->filter(fn ($k) => ! empty($k['id']))->flatMap(function ($k) use ($skillQuery) {
            $keep = collect($k['criteria'])->pluck('id')->filter();

            return SkillCriterion::where('skill_id', (clone $skillQuery)->whereKey($k['id'])->value('id'))
                ->whereNotIn('id', $keep)->withCount('scores')->get();
        });

        // Quitar algo que ya tiene calificaciones las borra: se pide confirmación explícita.
        $graded = $removedAspects->where('scores_count', '>', 0)->pluck('name')
            ->merge($removedSubjects->where('scores_count', '>', 0)->pluck('name'))
            ->merge($removedSkills->where('applications_count', '>', 0)->pluck('name'))
            ->merge($removedCriteria->where('scores_count', '>', 0)->pluck('description'));
        if ($graded->isNotEmpty() && ! $request->boolean('confirm_remove')) {
            throw ValidationException::withMessages([
                'confirm_remove' => 'Vas a quitar '.$graded->implode(', ').', que ya tienen calificaciones. Marca la casilla de confirmación para borrarlas.',
            ]);
        }

        DB::transaction(function () use ($group, $term, $campo, $data, $removedAspects, $removedSubjects, $removedSkills, $removedCriteria, $aspectQuery, $subjectQuery, $skillQuery) {
            $removedAspects->each->delete();
            $removedSubjects->each->delete();
            $removedSkills->each->delete();
            $removedCriteria->each->delete();

            foreach ($data['skills'] ?? [] as $i => $k) {
                $attrs = [...collect($k)->except(['id', 'criteria'])->all(), 'position' => $i];
                $existing = ! empty($k['id']) ? (clone $skillQuery)->find($k['id']) : null;
                $skill = $existing ? tap($existing)->update($attrs) : $group->skills()->create([...$attrs, 'campo' => $campo]);

                foreach ($k['criteria'] as $j => $c) {
                    $fields = [...collect($c)->except('id')->all(), 'position' => $j];
                    $crit = ! empty($c['id']) ? $skill->criteria()->find($c['id']) : null;
                    $crit ? $crit->update($fields) : $skill->criteria()->create($fields);
                }

                // Si cambiaron los rangos, las mediciones ya capturadas se vuelven a calificar.
                if ($skill->isMeasured()) {
                    $skill->scores()->whereNotNull('measure')->get()
                        ->each(fn ($s) => $s->update(['score' => $skill->scoreFor($s->measure)]));
                }
            }

            foreach ($data['aspects'] as $i => $a) {
                $attrs = ['name' => $a['name'], 'type' => $a['type'], 'weight' => $a['weight'], 'position' => $i];
                $existing = ! empty($a['id']) ? (clone $aspectQuery)->find($a['id']) : null;
                $existing
                    ? $existing->update($attrs)
                    : $group->termAspects()->create([...$attrs, 'term' => $term, 'campo' => $campo]);
            }

            foreach ($data['subjects'] ?? [] as $i => $s) {
                $existing = ! empty($s['id']) ? (clone $subjectQuery)->find($s['id']) : null;
                $existing
                    ? $existing->update(['name' => $s['name'], 'position' => $i])
                    : $group->subjects()->create(['campo' => $campo, 'name' => $s['name'], 'position' => $i]);
            }
        });

        return redirect()->route('campos.show', [$group, $campo])->with('status', Campos::short($campo).': evaluación del trimestre guardada.');
    }

    /**
     * Cómo se califica la habilidad:
     *  - level: la maestra elige el nivel.
     *  - measure: unidad + dónde EMPIEZA cada nivel (En proceso, Satisfactorio, Logrado). El corte
     *    entre 7 y 8 se calcula solo: la mitad entre el valor más bajo y el más alto de En proceso.
     *  - criteria: lista de criterios (al menos uno); la calificación es su promedio redondeado al nivel.
     */
    private function normalizeSkill(array $k): array
    {
        $labels = collect($k['level_labels'] ?? [])->map(fn ($v) => trim((string) $v))->filter()->all();
        $k['level_labels'] = $labels ?: null;
        $k['measure_unit'] = trim((string) ($k['measure_unit'] ?? '')) ?: null;
        $k['lower_is_better'] = (bool) ($k['lower_is_better'] ?? false);
        $k['criteria'] ??= [];
        $none = ['min_7' => null, 'min_8' => null, 'min_9' => null, 'min_10' => null];

        if ($k['grading'] === 'criteria') {
            if ($k['criteria'] === []) {
                throw ValidationException::withMessages(['skills' => "{$k['name']}: agrega al menos un criterio para calificarla por criterios."]);
            }

            return [...$k, ...$none, 'measure_unit' => null, 'lower_is_better' => false];
        }
        if ($k['grading'] === 'level') {
            return [...$k, ...$none, 'measure_unit' => null, 'lower_is_better' => false];
        }

        // measure
        if ($k['measure_unit'] === null) {
            throw ValidationException::withMessages(['skills' => "{$k['name']}: escribe qué se mide (ej. palabras por minuto)."]);
        }
        $cuts = array_map(fn ($c) => $k[$c] ?? null, ['min_7', 'min_9', 'min_10']);
        if (in_array(null, $cuts, true) || in_array('', $cuts, true)) {
            throw ValidationException::withMessages(['skills' => "{$k['name']}: indica dónde empieza En proceso, Satisfactorio y Logrado."]);
        }
        [$p, $s, $l] = array_map('floatval', $cuts);
        $ordered = $k['lower_is_better'] ? ($p > $s && $s > $l) : ($p < $s && $s < $l);
        if (! $ordered) {
            throw ValidationException::withMessages(['skills' => "{$k['name']}: los rangos deben ir ".($k['lower_is_better'] ? 'de mayor a menor (menos es mejor)' : 'de menor a mayor').', sin repetirse.']);
        }

        return [...$k, 'min_7' => $p, 'min_8' => Skill::splitProceso($p, $s, $k['lower_is_better']), 'min_9' => $s, 'min_10' => $l];
    }

    public function copyPrevious(Group $group, string $campo): RedirectResponse
    {
        $term = $group->term();
        abort_if($term === 1 || $group->termAspects()->where('term', $term)->where('campo', $campo)->exists(), 422);

        foreach ($group->termAspects()->where('term', $term - 1)->where('campo', $campo)->get() as $a) {
            $group->termAspects()->create([...$a->only(['campo', 'name', 'type', 'weight', 'position']), 'term' => $term]);
        }

        return redirect()->route('campos.edit', [$group, $campo])->with('status', 'Se copiaron los aspectos del Trimestre '.($term - 1).'. Ajusta lo que cambie.');
    }
}
