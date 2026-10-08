<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\TermBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $taken = $request->user()->groups()->pluck('shift');

        if ($taken->count() >= Group::MAX_PER_TEACHER) {
            return redirect()->route('dashboard')->withErrors(['shift' => 'Ya tienes grupo en ambos turnos.']);
        }

        // Si ya tiene el matutino, el nuevo grupo se propone vespertino (y viceversa).
        $shift = $request->query('shift');
        if (! array_key_exists((string) $shift, Group::SHIFTS) || $taken->contains($shift)) {
            $shift = collect(array_keys(Group::SHIFTS))->diff($taken)->first();
        }

        // Prellenar con la escuela del otro turno: muchas veces es la misma.
        $sibling = $request->user()->groups()->first();
        $group = new Group([
            'shift' => $shift,
            'school_name' => $sibling?->school_name,
            'school_cct' => $sibling?->school_cct,
            'school_zone' => $sibling?->school_zone,
            'school_year' => $sibling?->school_year,
        ]);

        return view('groups.form', ['group' => $group, 'taken' => $taken]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->groups()->count() >= Group::MAX_PER_TEACHER, 422, 'Máximo dos grupos.');

        $group = $request->user()->groups()->create($this->validated($request));

        return redirect()->route('students.index', $group)
            ->with('status', 'Grupo listo. Ahora sube la lista de alumnos.');
    }

    public function show(Group $group): View
    {
        $book = TermBook::for($group);

        return view('groups.show', compact('group', 'book'));
    }

    public function edit(Request $request, Group $group): View
    {
        $taken = $request->user()->groups()->whereKeyNot($group->id)->pluck('shift');

        return view('groups.form', compact('group', 'taken'));
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($this->validated($request, $group));

        return redirect()->route('grupos.show', $group)->with('status', 'Datos del centro de trabajo actualizados.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        // Borrar un grupo arrastra alumnos, proyectos y calificaciones: se exige escribir el grupo.
        $request->validate(['confirm_name' => ['required', Rule::in([$group->label(), $group->name])]], [
            'confirm_name.in' => 'Escribe el grupo exactamente como aparece para confirmar.',
        ]);

        $group->delete();
        $request->session()->forget('group_id');

        return redirect()->route('dashboard')->with('status', 'Grupo eliminado.');
    }

    private function validated(Request $request, ?Group $group = null): array
    {
        $request->merge([
            'name' => mb_strtoupper(trim((string) $request->input('name'))),
            'school_cct' => mb_strtoupper(trim((string) $request->input('school_cct'))) ?: null,
        ]);

        return $request->validate([
            'shift' => ['required', Rule::in(array_keys(Group::SHIFTS)),
                Rule::unique('groups')->where('user_id', $request->user()->id)->ignore($group?->id)],
            'grade' => ['required', 'integer', 'between:1,6'],
            'name' => ['required', 'string', 'max:10'],
            'school_name' => ['required', 'string', 'max:160'],
            'school_cct' => ['nullable', 'string', 'max:20'],
            'school_zone' => ['nullable', 'string', 'max:40'],
            'school_year' => ['nullable', 'string', 'max:40'],
        ], [
            'shift.unique' => 'Ya tienes un grupo en ese turno.',
            'name.required' => 'Indica la letra del grupo (ej. A).',
            'school_name.required' => 'Escribe el nombre de la escuela.',
        ]);
    }
}
