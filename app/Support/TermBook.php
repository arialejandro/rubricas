<?php

namespace App\Support;

use App\Models\AspectScore;
use App\Models\Criterion;
use App\Models\CriterionScore;
use App\Models\Group;
use App\Models\Product;
use App\Models\Project;
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

    public function hasProjectsAspect(string $campo): bool
    {
        return $this->aspectsFor($campo)->contains(fn ($a) => $a->isFromProjects());
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

    public function aspectValue(TermAspect $aspect, Student $s): ?float
    {
        return $aspect->isFromProjects()
            ? $this->projectsValue($aspect->campo, $s)
            : $this->aspectScore($s->id, $aspect->id);
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
     * Celdas por alumno en el campo: criterios de sus productos + aspectos directos + materias.
     * Un aspecto "Proyectos" sin ningún producto en el campo cuenta como 1 pendiente
     * (falta planear el proyecto o quitar el aspecto).
     */
    public function campoCellsPerStudent(string $campo): int
    {
        $n = $this->productsFor($campo)->sum(fn ($p) => $p->criteria->count())
            + $this->aspectsFor($campo)->reject->isFromProjects()->count()
            + $this->subjectsFor($campo)->count();

        return $n + ($this->projectsAspectWithoutProducts($campo) ? 1 : 0);
    }

    public function projectsAspectWithoutProducts(string $campo): bool
    {
        return $this->hasProjectsAspect($campo) && $this->productsFor($campo)->sum(fn ($p) => $p->criteria->count()) === 0;
    }

    public function campoMissing(string $campo, Student $s): int
    {
        $missing = $this->productsFor($campo)->sum(fn ($p) => $this->productMissing($p, $s))
            + $this->aspectsFor($campo)->reject->isFromProjects()->filter(fn ($a) => $this->aspectScore($s->id, $a->id) === null)->count()
            + $this->subjectsFor($campo)->filter(fn ($sub) => $this->subjectScore($s->id, $sub->id) === null)->count();

        return $missing + ($this->projectsAspectWithoutProducts($campo) ? 1 : 0);
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
    public function paint(Student $s, string $campo, ?Product $product = null, ?string $unitKey = null, ?int $unitMissing = null): array
    {
        $out = ['text' => [], 'level' => [], 'bar' => [], 'done' => []];

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
