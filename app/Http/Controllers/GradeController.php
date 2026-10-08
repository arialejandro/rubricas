<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Group;
use App\Models\Project;
use App\Support\Gradebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Autoguardado de una celda (alumno × aspecto). score vacío = borrar (vuelve a pendiente).
 * Responde con los totales recalculados para refrescar la pantalla sin recargar.
 */
class GradeController extends Controller
{
    public function update(Request $request, Group $group, Project $project): JsonResponse
    {
        $raw = str_replace(',', '.', trim((string) $request->input('score', '')));
        $request->merge(['score' => $raw === '' ? null : $raw]);

        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('group_id', $group->id)->where('active', true)],
            'criterion_id' => ['required', 'integer', Rule::exists('criteria', 'id')->where('project_id', $project->id)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
        ], [
            'score.numeric' => 'Escribe un número del 0 al 10.',
            'score.min' => 'La calificación mínima es 0.',
            'score.max' => 'La calificación máxima es 10.',
            'score.decimal' => 'Máximo dos decimales.',
        ]);

        $key = ['student_id' => $data['student_id'], 'criterion_id' => $data['criterion_id']];

        if ($data['score'] === null) {
            Grade::where($key)->delete();
        } else {
            Grade::updateOrCreate($key, ['score' => $data['score']]);
        }
        $project->touch(); // "proyectos recientes" = los que se están calificando

        $book = Gradebook::for($project);
        $student = $book->students->firstWhere('id', $data['student_id']);
        $criterion = $book->criteria->firstWhere('id', $data['criterion_id']);

        return response()->json([
            'score' => $book->score($student->id, $criterion->id),
            'student' => [
                'percent' => $book->finalPercent($student),
                'final' => $book->finalScore($student),
                'complete' => $book->isComplete($student),
                'missing' => $book->missingFor($student)->count(),
            ],
            'criterion_missing' => $book->missingForCriterion($criterion),
            'criterion_total' => $book->students->count(),
            'project' => [
                'id' => $project->id,
                'graded' => $book->gradedCells(),
                'total' => $book->totalCells(),
                'missing' => $book->missingCells(),
                'progress' => $book->progress(),
            ],
        ]);
    }
}
