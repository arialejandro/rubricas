<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Project;
use App\Support\Campos;
use App\Support\TermBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Group $group): View
    {
        $book = TermBook::for($group);

        return view('projects.index', compact('group', 'book'));
    }

    public function create(Request $request, Group $group): View
    {
        $project = new Project([
            'term' => $group->term(),
            'campo' => Campos::exists($request->query('campo')) ? $request->query('campo') : null,
            'pdas' => [''],
        ]);

        return view('projects.form', compact('group', 'project'));
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        $project = $group->projects()->create([...$this->validated($request), 'term' => $group->term()]);

        return redirect()->route('products.create', [$group, $project])
            ->with('status', 'Proyecto creado. Agrega sus productos y criterios de evaluación.');
    }

    public function show(Group $group, Project $project): View
    {
        $book = TermBook::for($group, $project->term);
        $project = $book->projects->firstWhere('id', $project->id);

        return view('projects.show', compact('group', 'project', 'book'));
    }

    public function edit(Group $group, Project $project): View
    {
        return view('projects.form', compact('group', 'project'));
    }

    public function update(Request $request, Group $group, Project $project): RedirectResponse
    {
        $project->update($this->validated($request));

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Proyecto actualizado.');
    }

    public function destroy(Request $request, Group $group, Project $project): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirma que quieres eliminar el proyecto y sus calificaciones.']);

        $project->delete();

        return redirect()->route('projects.index', $group)->with('status', 'Proyecto eliminado.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['pdas' => collect($request->input('pdas', []))->map(fn ($p) => trim((string) $p))->filter()->values()->all()]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'campo' => ['required', Rule::in(Campos::keys())],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'pdas' => ['required', 'array', 'min:1', 'max:'.Project::MAX_PDAS],
            'pdas.*' => ['string', 'max:1000'],
        ], [
            'campo.required' => 'Elige el campo formativo donde se plantea el proyecto.',
            'pdas.required' => 'Describe al menos un PDA.',
            'pdas.max' => 'Máximo '.Project::MAX_PDAS.' PDA por proyecto.',
        ]);
    }
}
