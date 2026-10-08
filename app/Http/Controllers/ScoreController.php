<?php

namespace App\Http\Controllers;

use App\Models\AspectScore;
use App\Models\Criterion;
use App\Models\CriterionScore;
use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectScore;
use App\Models\TermAspect;
use App\Support\Campos;
use App\Support\Level;
use App\Support\TermBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarda UNA celda (autoguardado del teclado). score vacío = borrar (vuelve a pendiente).
 *   kind=criterion → nivel 6–10 de un criterio de proyecto
 *   kind=aspect    → 0–10 de un aspecto directo del campo (Examen…)
 *   kind=subject   → 0–10 de una materia adicional en un trimestre
 * Responde con lo que hay que repintar (TermBook::paint).
 */
class ScoreController extends Controller
{
    public function __invoke(Request $request, Group $group): JsonResponse
    {
        $raw = str_replace(',', '.', trim((string) $request->input('score', '')));
        $request->merge(['score' => $raw === '' ? null : $raw]);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['criterion', 'aspect', 'subject'])],
            'id' => ['required', 'integer'],
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('group_id', $group->id)->where('active', true)],
            'term' => ['nullable', 'integer', Rule::in(Campos::TERMS)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
        ], [
            'score.numeric' => 'Escribe un número del 0 al 10.',
            'score.max' => 'La calificación máxima es 10.',
            'score.decimal' => 'Máximo dos decimales.',
        ]);

        $score = $data['score'] === null ? null : (float) $data['score'];
        $studentId = (int) $data['student_id'];

        [$term, $campo, $product, $unitKey, $model] = match ($data['kind']) {
            'criterion' => $this->criterion($group, (int) $data['id'], $score),
            'aspect' => $this->aspect($group, (int) $data['id']),
            'subject' => $this->subject($group, (int) $data['id'], $data['term'] ?? $group->term()),
        };

        $key = match ($data['kind']) {
            'criterion' => ['criterion_id' => $model->id, 'student_id' => $studentId],
            'aspect' => ['term_aspect_id' => $model->id, 'student_id' => $studentId],
            'subject' => ['subject_id' => $model->id, 'student_id' => $studentId, 'term' => $term],
        };
        $class = match ($data['kind']) {
            'criterion' => CriterionScore::class,
            'aspect' => AspectScore::class,
            'subject' => SubjectScore::class,
        };

        $score === null
            ? $class::where($key)->delete()
            : $class::updateOrCreate($key, ['score' => $score]);

        $book = TermBook::for($group, $term);
        $student = $book->students->firstWhere('id', $studentId);
        $missing = match ($data['kind']) {
            'criterion' => $book->criterionMissing($model),
            'aspect' => $book->aspectMissing($model),
            'subject' => $book->subjectMissing($model),
        };

        return response()->json([
            'score' => $score,
            'level' => Level::of($score),
            'paint' => $book->paint($student, $campo, $product, $unitKey, $missing),
        ]);
    }

    /** @return array{0:int, 1:string, 2:\App\Models\Product, 3:string, 4:Criterion} */
    private function criterion(Group $group, int $id, ?float $score): array
    {
        $criterion = Criterion::with('product.project')->findOrFail($id);
        abort_unless($criterion->product->project->group_id === $group->id, 404);

        // Un criterio se califica por nivel: 10 Logrado, 9 Satisfactorio, 8/7 En proceso, 6 Requiere apoyo.
        if ($score !== null && ! in_array($score, array_map('floatval', Level::CRITERION_SCORES), true)) {
            abort(response()->json([
                'message' => 'Elige un nivel: 10, 9, 8, 7 o 6.',
                'errors' => ['score' => ['Elige un nivel: 10, 9, 8, 7 o 6.']],
            ], 422));
        }

        return [$criterion->product->project->term, $criterion->product->campo, $criterion->product, "criterion:{$id}", $criterion];
    }

    private function aspect(Group $group, int $id): array
    {
        $aspect = $group->termAspects()->where('type', 'direct')->findOrFail($id);

        return [$aspect->term, $aspect->campo, null, "aspect:{$id}", $aspect];
    }

    private function subject(Group $group, int $id, int $term): array
    {
        $subject = $group->subjects()->findOrFail($id);

        return [$term, $subject->campo, null, "subject:{$id}", $subject];
    }
}
