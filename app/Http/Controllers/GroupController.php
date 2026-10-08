<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\Gradebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function create(): View
    {
        return view('groups.form', ['group' => new Group]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = $request->user()->groups()->create($this->validated($request));

        return redirect()->route('students.index', $group)
            ->with('status', 'Grupo creado. Ahora agrega a tus alumnos.');
    }

    public function show(Group $group): View
    {
        $projects = $group->projects()->withCount('criteria')->get();
        $summary = Gradebook::summaryForGroup($group);
        $studentCount = $group->activeStudents()->count();

        return view('groups.show', compact('group', 'projects', 'summary', 'studentCount'));
    }

    public function edit(Group $group): View
    {
        return view('groups.form', compact('group'));
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($this->validated($request));

        return redirect()->route('grupos.show', $group)->with('status', 'Grupo actualizado.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        // Borrar un grupo arrastra alumnos, proyectos y calificaciones: se exige escribir el nombre.
        $request->validate(['confirm_name' => ['required', Rule::in([$group->name])]], [
            'confirm_name.in' => 'Escribe el nombre exacto del grupo para confirmar.',
        ]);

        $group->delete();

        return redirect()->route('dashboard')->with('status', 'Grupo eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'school_year' => ['nullable', 'string', 'max:40'],
        ]);
    }
}
