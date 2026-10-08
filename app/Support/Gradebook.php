<?php

namespace App\Support;

use App\Models\Criterion;
use App\Models\Group;
use App\Models\Grade;
use App\Models\Project;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Matriz de calificaciones de un proyecto: alumnos activos × aspectos de la rúbrica.
 *
 * Regla de cálculo: cada aspecto se califica de 0 a 10 y aporta (calificación / 10) × peso%.
 * Ej.: un 8 en un aspecto que vale 20% aporta 16%. La suma de aportes es el % final;
 * dividido entre 10 da la nota final en escala 0–10.
 *
 * "Pendiente" = no hay fila en grades (un 0 capturado SÍ es calificación).
 */
class Gradebook
{
    /** @var array<int, array<int, float>> [student_id][criterion_id] => score */
    private array $scores = [];

    private function __construct(
        public readonly Project $project,
        /** @var Collection<int, Student> */
        public readonly Collection $students,
        /** @var Collection<int, Criterion> */
        public readonly Collection $criteria,
    ) {}

    public static function for(Project $project): self
    {
        $project->loadMissing('criteria', 'group');
        $students = $project->group->activeStudents()->get();

        $book = new self($project, $students, $project->criteria);

        Grade::query()
            ->whereIn('criterion_id', $book->criteria->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['criterion_id', 'student_id', 'score'])
            ->each(function (Grade $g) use ($book) {
                $book->scores[$g->student_id][$g->criterion_id] = $g->score;
            });

        return $book;
    }

    public function score(int $studentId, int $criterionId): ?float
    {
        return $this->scores[$studentId][$criterionId] ?? null;
    }

    /** @return Collection<int, Criterion> aspectos sin calificar del alumno */
    public function missingFor(Student $student): Collection
    {
        return $this->criteria->filter(fn (Criterion $c) => $this->score($student->id, $c->id) === null)->values();
    }

    public function isComplete(Student $student): bool
    {
        return $this->missingFor($student)->isEmpty();
    }

    public function missingForCriterion(Criterion $criterion): int
    {
        return $this->students->filter(fn (Student $s) => $this->score($s->id, $criterion->id) === null)->count();
    }

    public function totalCells(): int
    {
        return $this->students->count() * $this->criteria->count();
    }

    public function gradedCells(): int
    {
        return array_sum(array_map('count', $this->scores));
    }

    public function missingCells(): int
    {
        return $this->totalCells() - $this->gradedCells();
    }

    public function progress(): int
    {
        $total = $this->totalCells();

        return $total === 0 ? 0 : (int) floor($this->gradedCells() * 100 / $total);
    }

    /** % final (0–100). Los pendientes cuentan como 0, por eso se acompaña de isComplete(). */
    public function finalPercent(Student $student): float
    {
        return self::percentFrom($this->criteria, $this->scores[$student->id] ?? []);
    }

    public function finalScore(Student $student): float
    {
        return round($this->finalPercent($student) / 10, 2);
    }

    /** @param array<int, float> $scoresByCriterion */
    public static function percentFrom(Collection $criteria, array $scoresByCriterion): float
    {
        $total = 0.0;
        foreach ($criteria as $c) {
            $score = $scoresByCriterion[$c->id] ?? null;
            if ($score !== null) {
                $total += $score / 10 * $c->weight;
            }
        }

        return round($total, 2);
    }

    /**
     * Pendientes por proyecto de un grupo sin cargar la matriz completa (para tableros).
     *
     * @return array<int, array{total:int, graded:int, missing:int}> por project_id
     */
    public static function summaryForGroup(Group $group): array
    {
        $activeIds = $group->activeStudents()->pluck('id');
        $projects = $group->projects()->withCount('criteria')->get();

        $graded = Grade::query()
            ->join('criteria', 'criteria.id', '=', 'grades.criterion_id')
            ->whereIn('criteria.project_id', $projects->pluck('id'))
            ->whereIn('grades.student_id', $activeIds)
            ->groupBy('criteria.project_id')
            ->selectRaw('criteria.project_id, COUNT(*) as n')
            ->pluck('n', 'criteria.project_id');

        $out = [];
        foreach ($projects as $p) {
            $total = $activeIds->count() * $p->criteria_count;
            $done = (int) ($graded[$p->id] ?? 0);
            $out[$p->id] = ['total' => $total, 'graded' => $done, 'missing' => $total - $done];
        }

        return $out;
    }
}
