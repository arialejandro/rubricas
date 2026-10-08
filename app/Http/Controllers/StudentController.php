<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Group $group): View
    {
        $students = $group->students()->withCount('grades')->get();

        return view('students.index', compact('group', 'students'));
    }

    /**
     * Alta en lote: un alumno por renglón (se puede pegar la lista desde Excel/WhatsApp).
     * Acepta "12. Ana López", "12 Ana López", "12<TAB>Ana López" o solo el nombre.
     */
    public function store(Request $request, Group $group): RedirectResponse
    {
        $request->validate(['names' => ['required', 'string', 'max:20000']]);

        $existing = $group->students()->pluck('name')->map(fn ($n) => Str::lower($n))->flip();
        $added = 0;
        $skipped = [];

        foreach (preg_split('/\R/', $request->input('names')) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }

            $number = null;
            if (preg_match('/^(\d{1,3})[\s.\-)]+(.+)$/u', $line, $m)) {
                $number = (int) $m[1];
                $line = trim($m[2]);
            }

            $name = Str::limit($line, 250, '');
            if ($existing->has(Str::lower($name))) {
                $skipped[] = $name;
                continue;
            }

            $group->students()->create(['name' => $name, 'list_number' => $number]);
            $existing[Str::lower($name)] = true;
            $added++;
        }

        $msg = $added === 1 ? 'Se agregó 1 alumno.' : "Se agregaron {$added} alumnos.";
        if ($skipped) {
            $msg .= ' Ya existían (no se duplicaron): '.implode(', ', $skipped).'.';
        }

        return back()->with('status', $msg);
    }

    public function update(Request $request, Group $group, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:250', Rule::unique('students')->where('group_id', $group->id)->ignore($student->id)],
            'list_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'active' => ['required', 'boolean'],
        ], ['name.unique' => 'Ya hay un alumno con ese nombre en el grupo.']);

        $student->update($data);

        return back()->with('status', $student->active ? 'Alumno actualizado.' : "{$student->name} quedó dado de baja; sus calificaciones se conservan.");
    }

    public function destroy(Group $group, Student $student): RedirectResponse
    {
        if ($student->grades()->exists()) {
            return back()->withErrors(['student' => "{$student->name} ya tiene calificaciones. Dalo de baja en lugar de eliminarlo para no perderlas."]);
        }

        $student->delete();

        return back()->with('status', 'Alumno eliminado.');
    }
}
