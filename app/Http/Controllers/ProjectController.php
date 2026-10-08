<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Project;
use App\Support\Gradebook;
use App\Support\GroupOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Group $group): View
    {
        $overview = GroupOverview::for($group);

        return view('projects.index', compact('group', 'overview'));
    }

    public function create(Group $group): View
    {
        $project = new Project;
        $project->setRelation('criteria', collect());

        return view('projects.form', compact('group', 'project'));
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        [$data, $criteria] = $this->validated($request);

        $project = DB::transaction(function () use ($group, $data, $criteria) {
            $project = $group->projects()->create($data);
            foreach ($criteria as $i => $c) {
                $project->criteria()->create(['name' => $c['name'], 'weight' => $c['weight'], 'position' => $i]);
            }

            return $project;
        });

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Proyecto creado.');
    }

    public function show(Group $group, Project $project): View
    {
        $book = Gradebook::for($project);

        return view('projects.show', compact('group', 'project', 'book'));
    }

    public function edit(Group $group, Project $project): View
    {
        $project->load(['criteria' => fn ($q) => $q->withCount('grades')]);

        return view('projects.form', compact('group', 'project'));
    }

    public function update(Request $request, Group $group, Project $project): RedirectResponse
    {
        [$data, $criteria] = $this->validated($request);

        $keepIds = collect($criteria)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $removed = $project->criteria()->whereNotIn('id', $keepIds)->withCount('grades')->get();
        $removedGraded = $removed->where('grades_count', '>', 0);

        // Quitar un aspecto ya calificado borra esas calificaciones: se pide confirmación explícita.
        if ($removedGraded->isNotEmpty() && ! $request->boolean('confirm_remove')) {
            throw ValidationException::withMessages([
                'confirm_remove' => 'Vas a quitar aspectos que ya tienen calificaciones ('
                    .$removedGraded->pluck('name')->implode(', ')
                    .'). Marca la casilla de confirmación para borrarlas.',
            ]);
        }

        DB::transaction(function () use ($project, $data, $criteria, $removed) {
            $project->update($data);
            $removed->each->delete();

            foreach ($criteria as $i => $c) {
                $attrs = ['name' => $c['name'], 'weight' => $c['weight'], 'position' => $i];
                $existing = ! empty($c['id']) ? $project->criteria()->find($c['id']) : null;
                $existing ? $existing->update($attrs) : $project->criteria()->create($attrs);
            }
        });

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Proyecto actualizado.');
    }

    public function destroy(Request $request, Group $group, Project $project): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirma que quieres eliminar el proyecto y sus calificaciones.']);

        $project->delete();

        return redirect()->route('projects.index', $group)->with('status', 'Proyecto eliminado.');
    }

    /** @return array{0: array, 1: array<int, array{id:?int, name:string, weight:float}>} */
    private function validated(Request $request): array
    {
        // Se acepta coma decimal ("12,5") porque así se escribe en el teclado del iPad.
        $criteria = collect($request->input('criteria', []))
            ->filter(fn ($c) => trim($c['name'] ?? '') !== '' || trim($c['weight'] ?? '') !== '')
            ->map(fn ($c) => [...$c, 'weight' => str_replace(',', '.', trim($c['weight'] ?? ''))])
            ->values()
            ->all();
        $request->merge(['criteria' => $criteria]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'criteria' => ['required', 'array', 'min:1', 'max:30'],
            'criteria.*.id' => ['nullable', 'integer'],
            'criteria.*.name' => ['required', 'string', 'max:160'],
            'criteria.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ], [
            'criteria.required' => 'Agrega al menos un aspecto de evaluación.',
            'criteria.*.name.required' => 'Cada aspecto necesita nombre.',
            'criteria.*.weight.required' => 'Cada aspecto necesita su porcentaje.',
            'criteria.*.weight.gt' => 'El porcentaje debe ser mayor que 0.',
        ]);

        $sum = round(array_sum(array_column($data['criteria'], 'weight')), 2);
        if (abs($sum - 100) >= 0.01) {
            throw ValidationException::withMessages([
                'criteria' => "Los porcentajes suman {$sum}%; deben sumar exactamente 100%.",
            ]);
        }

        return [
            collect($data)->only(['name', 'description', 'due_date'])->all(),
            $data['criteria'],
        ];
    }
}
