<?php

namespace App\Support;

use App\Models\AspectScore;
use App\Models\Criterion;
use App\Models\CriterionScore;
use App\Models\Group;
use App\Models\Product;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillApplication;
use App\Models\SkillCriterionScore;
use App\Models\SkillScore;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectScore;
use App\Models\TermAspect;
use Illuminate\Support\Collection;

/**
 * Libreta de un grupo en un trimestre: todas las calificaciones y lo que se deriva de ellas.
 *
 * Cálculo de un campo formativo para un alumno:
 *   1. Producto   = promedio de sus criterios (6–10).
 *   2. "Proyectos" (aspecto tipo projects) = promedio de los productos evaluados en ESE campo,
 *      vengan del proyecto que vengan (transversalidad).
 *   3. Base del campo = Σ aspecto × peso%. Si faltan aspectos, se reparte entre los que hay
 *      (nota provisional, se marca como incompleta).
 *   4. Final del campo = promedio en partes iguales de la base y las materias adicionales
 *      del campo (Artes, Inglés, Ed. Física).
 *
 * Pendiente = celda sin capturar (criterio, aspecto directo o materia). 0 SÍ es calificación.
 * Cinco consultas en total; se recalcula completo en cada guardado (≤35 alumnos, es barato).
 */
class TermBook
{
    /** @var array<int, array<int, int>> [student][criterion] */
    private array $crit = [];

    /** @var array<int, array<int, float>> [student][aspect] */
    private array $asp = [];

    /** @var array<int, array<int, float>> [student][subject] */
    private array $sub = [];

    /** @var array<int, array<int, int>> [student][application] calificación (modos nivel y medición) */
    private array $skl = [];

    /** @var array<int, array<int, float>> [student][application] medición de habilidades medibles */
    private array $skm = [];

    /** @var array<int, array<int, array<int, int>>> [student][application][skill_criterion] */
    private array $skc = [];

    /** @var Collection<int, Collection<int, SkillApplication>> aplicaciones del trimestre por skill_id */
    public Collection $apps;

    /** @var Collection<int, Skill> */
    public Collection $skills;

    private function __construct(
        public readonly Group $group,
        public readonly int $term,
        /** @var Collection<int, Student> */
        public readonly Collection $students,
        /** @var Collection<int, TermAspect> */
        public readonly Collection $aspects,
        /** @var Collection<int, Subject> */
        public readonly Collection $subjects,
        /** @var Collection<int, Project> */
        public readonly Collection $projects,
    ) {}

    public static function for(Group $group, ?int $term = null): self
    {
        $term ??= $group->term();
        $students = $group->activeStudents()->get();
        $projects = $group->projects()->where('term', $term)->with('products.criteria')->get();
        $projects->each(fn (Project $p) => $p->products->each(fn (Product $pr) => $pr->setRelation('project', $p)));

        $book = new self(
            $group,
            $term,
            $students,
            $group->termAspects()->where('term', $term)->get(),
            $group->subjects()->get(),
            $projects,
        );

        $ids = $students->pluck('id');

        CriterionScore::whereIn('criterion_id', $book->allCriteria()->pluck('id'))->whereIn('student_id', $ids)
            ->get(['criterion_id', 'student_id', 'score'])
            ->each(function ($r) use ($book) { $book->crit[$r->student_id][$r->criterion_id] = $r->score; });

        AspectScore::whereIn('term_aspect_id', $book->aspects->pluck('id'))->whereIn('student_id', $ids)
            ->get(['term_aspect_id', 'student_id', 'score'])
            ->each(function ($r) use ($book) { $book->asp[$r->student_id][$r->term_aspect_id] = $r->score; });

        SubjectScore::whereIn('subject_id', $book->subjects->pluck('id'))->whereIn('student_id', $ids)->where('term', $term)
            ->get(['subject_id', 'student_id', 'score'])
            ->each(function ($r) use ($book) { $book->sub[$r->student_id][$r->subject_id] = $r->score; });

        // Habilidades: aplicaciones del trimestre + calificaciones por aplicación (y por criterio).
        $book->skills = $group->skills()->with('criteria')->get();
        $book->apps = SkillApplication::whereIn('skill_id', $book->skills->pluck('id'))->where('term', $term)
            ->orderByRaw('applied_on IS NULL, applied_on')->orderBy('id')->get()->groupBy('skill_id');
        $appIds = $book->apps->flatten()->pluck('id');

        SkillScore::whereIn('skill_application_id', $appIds)->whereIn('student_id', $ids)
            ->get(['skill_application_id', 'student_id', 'score', 'measure'])
            ->each(function ($r) use ($book) {
                $book->skl[$r->student_id][$r->skill_application_id] = $r->score;
                if ($r->measure !== null) {
                    $book->skm[$r->student_id][$r->skill_application_id] = $r->measure;
                }
            });

        SkillCriterionScore::whereIn('skill_application_id', $appIds)->whereIn('student_id', $ids)
            ->get(['skill_application_id', 'skill_criterion_id', 'student_id', 'score'])
            ->each(function ($r) use ($book) {
                $book->skc[$r->student_id][$r->skill_application_id][$r->skill_criterion_id] = $r->score;
            });

        return $book;
    }

    // ---------------------------------------------------------------- catálogo del trimestre

    /** @return Collection<int, TermAspect> */
    public function aspectsFor(string $campo): Collection
    {
        return $this->aspects->where('campo', $campo)->values();
    }

    /** @return Collection<int, Subject> */
    public function subjectsFor(string $campo): Collection
    {
        return $this->subjects->where('campo', $campo)->values();
    }

    /** @return Collection<int, Product> productos evaluados en el campo, de cualquier proyecto */
    public function productsFor(string $campo): Collection
    {
        return $this->projects->flatMap->products->where('campo', $campo)->values();
    }

    /** @return Collection<int, Criterion> */
    public function allCriteria(): Collection
    {
        return $this->projects->flatMap->products->flatMap->criteria;
    }

    /** @return Collection<int, TermAspect> aspectos que se capturan alumno por alumno */
    public function directAspectsFor(string $campo): Collection
    {
        return $this->aspectsFor($campo)->filter->isDirect()->values();
    }

    /**
     * @return Collection<int, Skill> habilidades prioritarias que CUENTAN en el campo este trimestre
     *                                (solo si el campo tiene un aspecto de tipo "skills")
     */
    public function skillsFor(string $campo): Collection
    {
        return $this->hasSkillsAspect($campo) ? $this->allSkillsFor($campo) : collect();
    }

    /** @return Collection<int, Skill> todas las habilidades del campo, cuenten o no */
    public function allSkillsFor(string $campo): Collection
    {
        return $this->skills->where('campo', $campo)->values();
    }

    public function hasProjectsAspect(string $campo): bool
    {
        return $this->aspectsFor($campo)->contains(fn ($a) => $a->isFromProjects());
    }

    public function hasSkillsAspect(string $campo): bool
    {
        return $this->aspectsFor($campo)->contains(fn ($a) => $a->isFromSkills());
    }

    public function totalWeight(string $campo): float
    {
        return round((float) $this->aspectsFor($campo)->sum('weight'), 2);
    }

    public function isConfigured(string $campo): bool
    {
        return $this->aspectsFor($campo)->isNotEmpty();
    }

    // ---------------------------------------------------------------- calificaciones capturadas

    public function criterionScore(int $studentId, int $criterionId): ?int
    {
        return $this->crit[$studentId][$criterionId] ?? null;
    }

    public function aspectScore(int $studentId, int $aspectId): ?float
    {
        return $this->asp[$studentId][$aspectId] ?? null;
    }

    public function subjectScore(int $studentId, int $subjectId): ?float
    {
        return $this->sub[$studentId][$subjectId] ?? null;
    }

    // ---------------------------------------------------------------- habilidades (por aplicación)

    /** @return Collection<int, SkillApplication> aplicaciones de la habilidad en el trimestre, en orden */
    public function applicationsFor(Skill $skill): Collection
    {
        return $this->apps->get($skill->id, collect())->values();
    }

    public function appMeasure(int $studentId, int $appId): ?float
    {
        return $this->skm[$studentId][$appId] ?? null;
    }

    public function appCriterionScore(int $studentId, int $appId, int $criterionId): ?int
    {
        return $this->skc[$studentId][$appId][$criterionId] ?? null;
    }

    /**
     * Calificación del alumno en una aplicación. Por criterios: promedio de los criterios
     * calificados, redondeado al nivel (En proceso queda en 7 u 8 según la mitad del rango).
     */
    public function appScore(Skill $skill, int $appId, int $studentId): ?int
    {
        if (! $skill->isByCriteria()) {
            return $this->skl[$studentId][$appId] ?? null;
        }

        $scores = array_values($this->skc[$studentId][$appId] ?? []);

        return $scores === [] ? null : Skill::scoreFromCriteria(array_sum($scores) / count($scores));
    }

    /** Pendientes del alumno en una aplicación (por criterios: cada criterio sin calificar). */
    public function appMissing(Skill $skill, int $appId, int $studentId): int
    {
        if (! $skill->isByCriteria()) {
            return isset($this->skl[$studentId][$appId]) ? 0 : 1;
        }
        if ($skill->criteria->isEmpty()) {
            return 1; // falta definir los criterios
        }

        return $skill->criteria->filter(fn ($c) => ! isset($this->skc[$studentId][$appId][$c->id]))->count();
    }

    /** Valor de la habilidad en el trimestre: PROMEDIO de sus aplicaciones calificadas. */
    public function skillScore(int $studentId, int $skillId): ?float
    {
        $skill = $this->skills->firstWhere('id', $skillId);
        if ($skill === null) {
            return null;
        }
        $scores = $this->applicationsFor($skill)->map(fn ($a) => $this->appScore($skill, $a->id, $studentId))->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    /** Celdas por alumno de una habilidad: aplicaciones × (criterios o 1). Sin aplicaciones cuenta como 1. */
    public function skillCells(Skill $skill): int
    {
        $perApp = $skill->isByCriteria() ? max(1, $skill->criteria->count()) : 1;

        return max(1, $this->applicationsFor($skill)->count()) * $perApp;
    }

    public function skillMissingFor(Skill $skill, Student $s): int
    {
        $apps = $this->applicationsFor($skill);
        if ($apps->isEmpty()) {
            return $this->skillCells($skill);
        }

        return $apps->sum(fn ($a) => $this->appMissing($skill, $a->id, $s->id));
    }

    // ---------------------------------------------------------------- derivadas

    public function productScore(Product $product, Student $s): ?float
    {
        $scores = $product->criteria->map(fn ($c) => $this->criterionScore($s->id, $c->id))->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    public function productMissing(Product $product, Student $s): int
    {
        return $product->criteria->filter(fn ($c) => $this->criterionScore($s->id, $c->id) === null)->count();
    }

    /** Valor del aspecto "Proyectos" de un campo: promedio de sus productos (transversal). */
    public function projectsValue(string $campo, Student $s): ?float
    {
        $scores = $this->productsFor($campo)->map(fn ($p) => $this->productScore($p, $s))->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    /** Valor del aspecto "Habilidades prioritarias": promedio de las habilidades calificadas. */
    public function skillsValue(string $campo, Student $s): ?float
    {
        $scores = $this->allSkillsFor($campo)->map(fn ($k) => $this->skillScore($s->id, $k->id))->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    public function aspectValue(TermAspect $aspect, Student $s): ?float
    {
        return match ($aspect->type) {
            'projects' => $this->projectsValue($aspect->campo, $s),
            'skills' => $this->skillsValue($aspect->campo, $s),
            default => $this->aspectScore($s->id, $aspect->id),
        };
    }

    /** Σ aspecto × peso, repartiendo el peso entre los aspectos que ya tienen valor. */
    public function campoBase(string $campo, Student $s): ?float
    {
        $sum = 0.0;
        $weight = 0.0;
        foreach ($this->aspectsFor($campo) as $a) {
            $v = $this->aspectValue($a, $s);
            if ($v !== null) {
                $sum += $v * $a->weight;
                $weight += $a->weight;
            }
        }

        return $weight > 0 ? round($sum / $weight, 2) : null;
    }

    /** Base del campo promediada en partes iguales con las materias adicionales capturadas. */
    public function campoFinal(string $campo, Student $s): ?float
    {
        $base = $this->campoBase($campo, $s);
        if ($base === null) {
            return null;
        }

        $parts = $this->subjectsFor($campo)->map(fn ($sub) => $this->subjectScore($s->id, $sub->id))
            ->filter(fn ($v) => $v !== null)->prepend($base);

        return round($parts->avg(), 2);
    }

    public function generalAverage(Student $s): ?float
    {
        $finals = collect(Campos::keys())->map(fn ($c) => $this->campoFinal($c, $s))->filter(fn ($v) => $v !== null);

        return $finals->isEmpty() ? null : round($finals->avg(), 2);
    }

    /** Promedio del grupo en el campo (solo alumnos con calificación). */
    public function campoAverage(string $campo): ?float
    {
        $finals = $this->students->map(fn ($s) => $this->campoFinal($campo, $s))->filter(fn ($v) => $v !== null);

        return $finals->isEmpty() ? null : round($finals->avg(), 2);
    }

    // ---------------------------------------------------------------- pendientes

    /**
     * Celdas por alumno en el campo: criterios de sus productos + aspectos directos + habilidades
     * (si el campo las evalúa) + materias. Un aspecto "Proyectos" sin productos, o "Habilidades"
     * sin habilidades, cuenta como 1 pendiente (falta planearlo o quitar el aspecto).
     */
    public function campoCellsPerStudent(string $campo): int
    {
        $n = $this->productsFor($campo)->sum(fn ($p) => $p->criteria->count())
            + $this->directAspectsFor($campo)->count()
            + $this->skillsFor($campo)->sum(fn ($k) => $this->skillCells($k))
            + $this->subjectsFor($campo)->count();

        return $n + $this->structuralGaps($campo);
    }

    public function projectsAspectWithoutProducts(string $campo): bool
    {
        return $this->hasProjectsAspect($campo) && $this->productsFor($campo)->sum(fn ($p) => $p->criteria->count()) === 0;
    }

    public function skillsAspectWithoutSkills(string $campo): bool
    {
        return $this->hasSkillsAspect($campo) && $this->allSkillsFor($campo)->isEmpty();
    }

    private function structuralGaps(string $campo): int
    {
        return ($this->projectsAspectWithoutProducts($campo) ? 1 : 0) + ($this->skillsAspectWithoutSkills($campo) ? 1 : 0);
    }

    public function campoMissing(string $campo, Student $s): int
    {
        $missing = $this->productsFor($campo)->sum(fn ($p) => $this->productMissing($p, $s))
            + $this->directAspectsFor($campo)->filter(fn ($a) => $this->aspectScore($s->id, $a->id) === null)->count()
            + $this->skillsFor($campo)->sum(fn ($k) => $this->skillMissingFor($k, $s))
            + $this->subjectsFor($campo)->filter(fn ($sub) => $this->subjectScore($s->id, $sub->id) === null)->count();

        return $missing + $this->structuralGaps($campo);
    }

    /** Alumnos con algo pendiente en la habilidad (en cualquiera de sus aplicaciones). */
    public function skillMissing(Skill $skill): int
    {
        return $this->students->filter(fn ($s) => $this->skillMissingFor($skill, $s) > 0)->count();
    }

    /** Alumnos sin calificar en una aplicación (o en un criterio de esa aplicación). */
    public function appStudentsMissing(Skill $skill, int $appId, ?int $criterionId = null): int
    {
        return $this->students->filter(fn ($s) => $criterionId
            ? $this->appCriterionScore($s->id, $appId, $criterionId) === null
            : $this->appMissing($skill, $appId, $s->id) > 0)->count();
    }

    public function campoComplete(string $campo, Student $s): bool
    {
        return $this->isConfigured($campo) && $this->campoMissing($campo, $s) === 0;
    }

    public function missingFor(Student $s): int
    {
        return collect(Campos::keys())->sum(fn ($c) => $this->campoMissing($c, $s));
    }

    /** @return array{graded:int, total:int, missing:int, progress:int} */
    public function campoProgress(string $campo): array
    {
        $total = $this->campoCellsPerStudent($campo) * $this->students->count();
        $missing = $this->students->sum(fn ($s) => $this->campoMissing($campo, $s));

        return self::progressArray($total, $missing);
    }

    /** @return array{graded:int, total:int, missing:int, progress:int} */
    public function progress(): array
    {
        $total = collect(Campos::keys())->sum(fn ($c) => $this->campoCellsPerStudent($c)) * $this->students->count();
        $missing = $this->students->sum(fn ($s) => $this->missingFor($s));

        return self::progressArray($total, $missing);
    }

    public function criterionMissing(Criterion $c): int
    {
        return $this->students->filter(fn ($s) => $this->criterionScore($s->id, $c->id) === null)->count();
    }

    public function aspectMissing(TermAspect $a): int
    {
        return $this->students->filter(fn ($s) => $this->aspectScore($s->id, $a->id) === null)->count();
    }

    public function subjectMissing(Subject $sub): int
    {
        return $this->students->filter(fn ($s) => $this->subjectScore($s->id, $sub->id) === null)->count();
    }

    /** @return array{graded:int, total:int, missing:int, progress:int} */
    public function projectProgress(Project $project): array
    {
        $criteria = $project->products->flatMap->criteria;
        $total = $criteria->count() * $this->students->count();
        $missing = $criteria->sum(fn ($c) => $this->criterionMissing($c));

        return self::progressArray($total, $missing);
    }

    /** @return Collection<int, array{student:Student, missing:int}> */
    public function studentsWithPending(): Collection
    {
        return $this->students
            ->map(fn ($s) => ['student' => $s, 'missing' => $this->missingFor($s)])
            ->filter(fn ($r) => $r['missing'] > 0)
            ->sortByDesc('missing')
            ->values();
    }

    private static function progressArray(int $total, int $missing): array
    {
        $graded = $total - $missing;

        return [
            'graded' => $graded,
            'total' => $total,
            'missing' => $missing,
            'progress' => $total === 0 ? 0 : (int) floor($graded * 100 / $total),
        ];
    }

    // ---------------------------------------------------------------- refresco en pantalla

    /**
     * Valores a repintar tras guardar una celda (ver resources/js/keypad.js → paint()).
     * Cada clave corresponde a un data-paint="…" en las vistas.
     *
     * @return array{text: array<string,string>, level: array<string,?string>, bar: array<string,int>, done: array<string,bool>}
     */
    public function paint(Student $s, string $campo, ?Product $product = null, ?string $unitKey = null, ?int $unitMissing = null, ?Skill $skill = null, ?int $appId = null): array
    {
        $out = ['text' => [], 'level' => [], 'bar' => [], 'done' => []];

        if ($skill) {
            $avg = $this->skillScore($s->id, $skill->id);
            $out['text']["skill:{$skill->id}:{$s->id}"] = self::fmt($avg);
            $out['level']["skill:{$skill->id}:{$s->id}"] = Level::of($avg);
            if ($appId) {
                $as = $this->appScore($skill, $appId, $s->id);
                $complete = $this->appMissing($skill, $appId, $s->id) === 0;
                $out['text']["app:{$appId}:{$s->id}"] = $as === null ? '—' : $as.' · '.$skill->levelLabel(Level::of($as));
                $out['level']["app:{$appId}:{$s->id}"] = $as === null ? null : ($complete ? Level::of($as) : 'provisional');
            }
        }

        $final = $this->campoFinal($campo, $s);
        $base = $this->campoBase($campo, $s);
        $complete = $this->campoComplete($campo, $s);
        $missing = $this->campoMissing($campo, $s);

        $out['text']["final:{$campo}:{$s->id}"] = self::fmt($final);
        $out['level']["final:{$campo}:{$s->id}"] = $complete ? Level::of($final) : 'provisional';
        $out['text']["base:{$campo}:{$s->id}"] = self::fmt($base);
        $out['level']["base:{$campo}:{$s->id}"] = Level::of($base);
        $out['text']["status:{$campo}:{$s->id}"] = $complete ? 'Completo' : "Faltan {$missing}";
        $out['level']["status:{$campo}:{$s->id}"] = $complete ? 'done' : 'pending';
        $out['done']["row:{$campo}:{$s->id}"] = $complete;

        $pv = $this->projectsValue($campo, $s);
        $out['text']["projects:{$campo}:{$s->id}"] = self::fmt($pv);
        $out['level']["projects:{$campo}:{$s->id}"] = Level::of($pv);

        $sv = $this->skillsValue($campo, $s);
        $out['text']["skills:{$campo}:{$s->id}"] = self::fmt($sv);
        $out['level']["skills:{$campo}:{$s->id}"] = Level::of($sv);

        if ($product) {
            $ps = $this->productScore($product, $s);
            $out['text']["product:{$product->id}:{$s->id}"] = self::fmt($ps);
            $out['level']["product:{$product->id}:{$s->id}"] = $this->productMissing($product, $s) ? 'provisional' : Level::of($ps);
        }

        if ($unitKey !== null && $unitMissing !== null) {
            $total = max(1, $this->students->count());
            $out['text']["missing:{$unitKey}"] = $unitMissing === 0 ? 'Completo' : "{$unitMissing} sin calificar";
            $out['level']["missing:{$unitKey}"] = $unitMissing === 0 ? 'done' : 'pending';
            $out['text']["count:{$unitKey}"] = ($this->students->count() - $unitMissing).'/'.$this->students->count();
            $out['bar'][$unitKey] = (int) floor(($this->students->count() - $unitMissing) * 100 / $total);
        }

        $cp = $this->campoProgress($campo);
        $out['bar']["campo:{$campo}"] = $cp['progress'];
        $out['text']["campo-progress:{$campo}"] = $cp['progress'].'%';

        $avg = $this->generalAverage($s);
        $out['text']["general:{$s->id}"] = self::fmt($avg);
        $out['level']["general:{$s->id}"] = Level::of($avg);

        return $out;
    }

    public static function fmt(?float $n): string
    {
        return $n === null ? '—' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
