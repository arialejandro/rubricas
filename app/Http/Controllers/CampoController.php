<?php

namespace App\Http\Controllers;

use App\Models\Group;
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
        $skills = Campos::hasSkills($campo) ? $group->skills()->where('campo', $campo)->withCount('scores')->get() : collect();
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
        $levelRules = collect(array_keys(Level::LEVELS))->mapWithKeys(fn ($l) => ['skills.*.'.Level::column($l) => ['nullable', 'string', 'max:1000']])->all();

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

        $aspectQuery = $group->termAspects()->where('term', $term)->where('campo', $campo);
        $removedAspects = (clone $aspectQuery)->whereNotIn('id', collect($data['aspects'])->pluck('id')->filter())->withCount('scores')->get();
        $subjectQuery = $group->subjects()->where('campo', $campo);
        $removedSubjects = (clone $subjectQuery)->whereNotIn('id', collect($data['subjects'] ?? [])->pluck('id')->filter())->withCount('scores')->get();
        $skillQuery = $group->skills()->where('campo', $campo);
        $removedSkills = Campos::hasSkills($campo)
            ? (clone $skillQuery)->whereNotIn('id', collect($data['skills'] ?? [])->pluck('id')->filter())->withCount('scores')->get()
            : collect();

        // Quitar algo que ya tiene calificaciones las borra: se pide confirmación explícita.
        $graded = $removedAspects->where('scores_count', '>', 0)->pluck('name')
            ->merge($removedSubjects->where('scores_count', '>', 0)->pluck('name'))
            ->merge($removedSkills->where('scores_count', '>', 0)->pluck('name'));
        if ($graded->isNotEmpty() && ! $request->boolean('confirm_remove')) {
            throw ValidationException::withMessages([
                'confirm_remove' => 'Vas a quitar '.$graded->implode(', ').', que ya tienen calificaciones. Marca la casilla de confirmación para borrarlas.',
            ]);
        }

        DB::transaction(function () use ($group, $term, $campo, $data, $removedAspects, $removedSubjects, $removedSkills, $aspectQuery, $subjectQuery, $skillQuery) {
            $removedAspects->each->delete();
            $removedSubjects->each->delete();
            $removedSkills->each->delete();

            foreach ($data['skills'] ?? [] as $i => $k) {
                $attrs = [...collect($k)->except('id')->all(), 'position' => $i];
                $existing = ! empty($k['id']) ? (clone $skillQuery)->find($k['id']) : null;
                $existing ? $existing->update($attrs) : $group->skills()->create([...$attrs, 'campo' => $campo]);
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
