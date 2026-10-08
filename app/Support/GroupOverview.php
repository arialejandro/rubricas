<?php

namespace App\Support;

use App\Models\Grade;
use App\Models\Group;
use App\Models\Project;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Foto completa de un grupo para tableros: avance por proyecto, pendientes por alumno
 * y totales. Tres consultas (alumnos, proyectos+aspectos, calificaciones), sin N+1.
 */
class GroupOverview
{
    /** @var array<int, array<int, float>> [student_id][criterion_id] => score */
    private array $scores = [];

    private function __construct(
        public readonly Group $group,
        /** @var Collection<int, Student> */
        public readonly Collection $students,
        /** @var Collection<int, Project> */
        public readonly Collection $projects,
    ) {}

    public static function for(Group $group): self
    {
        $students = $group->activeStudents()->get();
        $projects = $group->projects()->with('criteria')->get();
        $self = new self($group, $students, $projects);

        Grade::query()
            ->whereIn('criterion_id', $projects->flatMap->criteria->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['criterion_id', 'student_id', 'score'])
            ->each(function (Grade $g) use ($self) {
                $self->scores[$g->student_id][$g->criterion_id] = $g->score;
            });

        return $self;
    }

    /** @return array{total:int, graded:int, missing:int, progress:int} */
    public function projectSummary(Project $project): array
    {
        $total = $this->students->count() * $project->criteria->count();
        $graded = 0;
        foreach ($this->students as $s) {
            foreach ($project->criteria as $c) {
                if (isset($this->scores[$s->id][$c->id])) {
                    $graded++;
                }
            }
        }

        return [
            'total' => $total,
            'graded' => $graded,
            'missing' => $total - $graded,
            'progress' => $total === 0 ? 0 : (int) floor($graded * 100 / $total),
        ];
    }

    public function missingForStudent(Student $student): int
    {
        $missing = 0;
        foreach ($this->projects as $p) {
            foreach ($p->criteria as $c) {
                if (! isset($this->scores[$student->id][$c->id])) {
                    $missing++;
                }
            }
        }

        return $missing;
    }

    /** Promedio de las notas finales del alumno en los proyectos que ya tiene completos. */
    public function averageForStudent(Student $student): ?float
    {
        $finals = $this->projects
            ->filter(fn (Project $p) => $p->criteria->isNotEmpty()
                && $p->criteria->every(fn ($c) => isset($this->scores[$student->id][$c->id])))
            ->map(fn (Project $p) => Gradebook::percentFrom($p->criteria, $this->scores[$student->id]) / 10);

        return $finals->isEmpty() ? null : round($finals->avg(), 2);
    }

    /** @return Collection<int, array{student:Student, missing:int}> alumnos con pendientes, más atrasados primero */
    public function studentsWithPending(): Collection
    {
        return $this->students
            ->map(fn (Student $s) => ['student' => $s, 'missing' => $this->missingForStudent($s)])
            ->filter(fn ($row) => $row['missing'] > 0)
            ->sortByDesc('missing')
            ->values();
    }

    /** @return array{students:int, cells:int, graded:int, missing:int, progress:int, complete:int, pending:int, projects:int} */
    public function totals(): array
    {
        $cells = $this->students->count() * $this->projects->sum(fn ($p) => $p->criteria->count());
        $graded = array_sum(array_map('count', $this->scores));
        $pending = $this->studentsWithPending()->count();

        return [
            'students' => $this->students->count(),
            'projects' => $this->projects->count(),
            'cells' => $cells,
            'graded' => $graded,
            'missing' => $cells - $graded,
            'progress' => $cells === 0 ? 0 : (int) floor($graded * 100 / $cells),
            'complete' => $this->projects->isEmpty() ? 0 : $this->students->count() - $pending,
            'pending' => $pending,
        ];
    }

    /** @return Collection<int, Project> */
    public function recentProjects(int $limit = 4): Collection
    {
        return $this->projects->sortByDesc('updated_at')->take($limit)->values();
    }
}
