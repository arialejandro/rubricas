<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Support\TermBook;
use App\Support\StudentListImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentController extends Controller
{
    public function index(Group $group): View
    {
        $book = TermBook::for($group);
        $students = $group->students()->get();
        $missing = $book->students->mapWithKeys(fn ($s) => [$s->id => $book->missingFor($s)]);
        $averages = $book->students->mapWithKeys(fn ($s) => [$s->id => $book->generalAverage($s)]);

        return view('students.index', compact('group', 'students', 'missing', 'averages'));
    }

    /** Ficha del alumno en el trimestre: cada campo con su desglose, capturable con el teclado. */
    public function show(Group $group, Student $student): View
    {
        $book = TermBook::for($group);

        return view('students.show', compact('group', 'student', 'book'));
    }

    /** Alta pegando texto (respaldo del Excel). */
    public function store(Request $request, Group $group): RedirectResponse
    {
        $request->validate(['names' => ['required', 'string', 'max:20000']]);

        return $this->report($group, StudentListImporter::fromText($request->input('names')));
    }

    public function import(Request $request, Group $group): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx,xls,csv'],
        ], [
            'file.required' => 'Elige el archivo de Excel con la lista.',
            'file.extensions' => 'El archivo debe ser Excel (.xlsx, .xls) o .csv.',
        ]);

        try {
            $rows = StudentListImporter::fromUpload($request->file('file'));
        } catch (Throwable) {
            return back()->withErrors(['file' => 'No se pudo leer el archivo. Revisa que sea un Excel válido o usa la plantilla.']);
        }

        return $this->report($group, $rows);
    }

    public function template(Group $group): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx(StudentListImporter::template()))->save('php://output'),
            'plantilla-alumnos.xlsx',
        );
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
        if ($student->hasScores()) {
            return back()->withErrors(['student' => "{$student->name} ya tiene calificaciones. Dalo de baja en lugar de eliminarlo para no perderlas."]);
        }

        $student->delete();

        return redirect()->route('students.index', $group)->with('status', 'Alumno eliminado.');
    }

    private function report(Group $group, array $rows): RedirectResponse
    {
        if ($rows === []) {
            return back()->withErrors(['file' => 'No se encontraron nombres. La lista necesita una columna "Nombre" (y opcional "N.L.").']);
        }

        $r = StudentListImporter::apply($group, $rows);
        $parts = array_filter([
            $r['added'] ? "{$r['added']} nuevos" : null,
            $r['updated'] ? "{$r['updated']} con N.L. actualizado" : null,
            $r['unchanged'] ? "{$r['unchanged']} ya estaban" : null,
        ]);

        return redirect()->route('students.index', $group)->with('status', 'Lista cargada: '.implode(' · ', $parts).'.');
    }
}
