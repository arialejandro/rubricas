<?php

namespace App\Http\Controllers;

use App\Models\AspectScore;
use App\Models\Criterion;
use App\Models\CriterionScore;
use App\Models\Group;
use App\Models\Skill;
use App\Models\SkillApplication;
use App\Models\SkillCriterionScore;
use App\Models\SkillScore;
use App\Models\SubjectScore;
use App\Support\Campos;
use App\Support\Level;
use App\Support\TermBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarda UNA celda (autoguardado del teclado). Vacío = borrar (vuelve a pendiente).
 *   kind=criterion → nivel 6–10 de un criterio de proyecto              (id = criterio)
 *   kind=aspect    → 0–10 de un aspecto directo del campo (Examen…)     (id = aspecto)
 *   kind=subject   → 0–10 de una materia adicional en un trimestre      (id = materia, term)
 *   kind=skill     → una aplicación de una habilidad: nivel 6–10, o la  (id = aplicación)
 *                    medición si es medible (la calificación se calcula)
 *   kind=skillcrit → nivel 6–10 de un criterio de habilidad en una      (id = criterio, app = aplicación)
 *                    aplicación
 * Responde con lo que hay que repintar (TermBook::paint).
 */
class ScoreController extends Controller
{
    public function __invoke(Request $request, Group $group): JsonResponse
    {
        $raw = str_replace(',', '.', trim((string) $request->input('score', '')));
        $rawMeasure = str_replace(',', '.', trim((string) $request->input('measure', '')));
        $request->merge(['score' => $raw === '' ? null : $raw, 'measure' => $rawMeasure === '' ? null : $rawMeasure]);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['criterion', 'aspect', 'subject', 'skill', 'skillcrit'])],
            'id' => ['required', 'integer'],
            'app' => ['nullable', 'integer'],
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('group_id', $group->id)->where('active', true)],
            'term' => ['nullable', 'integer', Rule::in(Campos::TERMS)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
            'measure' => ['nullable', 'numeric', 'min:0', 'max:99999', 'decimal:0,2'],
        ], [
            'measure.numeric' => 'Escribe la medición con números.',
            'score.numeric' => 'Escribe un número del 0 al 10.',
            'score.max' => 'La calificación máxima es 10.',
            'score.decimal' => 'Máximo dos decimales.',
        ]);

        $score = $data['score'] === null ? null : (float) $data['score'];
        $measure = null;
        $sid = (int) $data['student_id'];
        $product = null;
        $skill = null;
        $appId = null;

        switch ($data['kind']) {
            case 'criterion':
                $model = Criterion::with('product.project')->findOrFail($data['id']);
                abort_unless($model->product->project->group_id === $group->id, 404);
                $this->ensureLevel($score);
                [$term, $campo, $product] = [$model->product->project->term, $model->product->campo, $model->product];
                $this->put(CriterionScore::class, ['criterion_id' => $model->id, 'student_id' => $sid], $score, ['score' => $score]);
                break;

            case 'aspect':
                $model = $group->termAspects()->where('type', 'direct')->findOrFail($data['id']);
                [$term, $campo] = [$model->term, $model->campo];
                $this->put(AspectScore::class, ['term_aspect_id' => $model->id, 'student_id' => $sid], $score, ['score' => $score]);
                break;

            case 'subject':
                $model = $group->subjects()->findOrFail($data['id']);
                [$term, $campo] = [$data['term'] ?? $group->term(), $model->campo];
                $this->put(SubjectScore::class, ['subject_id' => $model->id, 'student_id' => $sid, 'term' => $term], $score, ['score' => $score]);
                break;

            case 'skill':
                $app = $this->application($group, (int) $data['id']);
                $skill = $app->skill;
                abort_if($skill->isByCriteria(), 422, 'Esta habilidad se califica por criterios.');
                // Medible: se guarda la medición y la calificación sale de los rangos de la maestra.
                if ($skill->isMeasured()) {
                    $measure = $data['measure'] === null ? null : (float) $data['measure'];
                    $score = $measure === null ? null : (float) $skill->scoreFor($measure);
                } else {
                    $this->ensureLevel($score);
                }
                [$term, $campo, $appId, $model] = [$app->term, $skill->campo, $app->id, $app];
                $this->put(SkillScore::class, ['skill_application_id' => $app->id, 'student_id' => $sid], $score,
                    ['score' => $score, 'measure' => $measure, 'skill_id' => $skill->id, 'term' => $app->term]);
                break;

            case 'skillcrit':
                $app = $this->application($group, (int) ($data['app'] ?? 0));
                $skill = $app->skill;
                $model = $skill->criteria()->findOrFail($data['id']);
                $this->ensureLevel($score);
                [$term, $campo, $appId] = [$app->term, $skill->campo, $app->id];
                $this->put(SkillCriterionScore::class,
                    ['skill_application_id' => $app->id, 'skill_criterion_id' => $model->id, 'student_id' => $sid], $score, ['score' => $score]);
                break;
        }

        $book = TermBook::for($group, $term);
        $student = $book->students->firstWhere('id', $sid);
        $skill = $skill ? $book->skills->firstWhere('id', $skill->id) : null;

        [$unitKey, $missing] = match ($data['kind']) {
            'criterion' => ["criterion:{$model->id}", $book->criterionMissing($model)],
            'aspect' => ["aspect:{$model->id}", $book->aspectMissing($model)],
            'subject' => ["subject:{$model->id}", $book->subjectMissing($model)],
            'skill' => ["app:{$appId}", $book->appStudentsMissing($skill, $appId)],
            'skillcrit' => ["appcrit:{$appId}:{$model->id}", $book->appStudentsMissing($skill, $appId, $model->id)],
        };

        return response()->json([
            'score' => $score,
            'measure' => $measure,
            'level' => Level::of($score),
            'paint' => $book->paint($student, $campo, $product, $unitKey, $missing, $skill, $appId),
        ]);
    }

    private function put(string $class, array $key, ?float $score, array $values): void
    {
        $score === null ? $class::where($key)->delete() : $class::updateOrCreate($key, $values);
    }

    private function application(Group $group, int $id): SkillApplication
    {
        $app = SkillApplication::with('skill.criteria')->findOrFail($id);
        abort_unless($app->skill->group_id === $group->id, 404);

        return $app;
    }

    /** Criterios y habilidades se califican por nivel: 10 Logrado, 9 Satisfactorio, 8/7 En proceso, 6 Requiere apoyo. */
    private function ensureLevel(?float $score): void
    {
        if ($score !== null && ! in_array($score, array_map('floatval', Level::CRITERION_SCORES), true)) {
            abort(response()->json([
                'message' => 'Elige un nivel: 10, 9, 8, 7 o 6.',
                'errors' => ['score' => ['Elige un nivel: 10, 9, 8, 7 o 6.']],
            ], 422));
        }
    }
}
