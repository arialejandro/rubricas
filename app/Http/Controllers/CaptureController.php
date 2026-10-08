<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Group;
use App\Models\Product;
use App\Models\Project;
use App\Models\Subject;
use App\Models\TermAspect;
use App\Support\Campos;
use App\Support\TermBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pantalla de captura "punto de venta": una lista de alumnos y el teclado.
 * Tres cosas se capturan así: un criterio de proyecto (por nivel 6–10), un aspecto directo
 * del campo (0–10, p. ej. Examen) y una materia adicional (0–10, p. ej. Inglés).
 */
class CaptureController extends Controller
{
    public function criterion(Group $group, Project $project, Product $product, Criterion $criterion): View
    {
        $book = TermBook::for($group, $project->term);
        $criteria = $product->criteria;

        return view('capture', [
            'group' => $group,
            'book' => $book,
            'campo' => $product->campo,
            'mode' => 'levels',
            'kind' => 'criterion',
            'unit' => $criterion,
            'unitKey' => "criterion:{$criterion->id}",
            'title' => $criterion->description,
            'subtitle' => $product->name.($product->instrument ? ' · '.$product->instrument : ''),
            'missing' => $book->criterionMissing($criterion),
            'value' => fn ($s) => $book->criterionScore($s->id, $criterion->id),
            'descriptors' => $criterion->descriptors(),
            'back' => [route('projects.show', [$group, $project]), $project->name],
            'tabs' => $criteria->map(fn ($c) => [
                'label' => $c->description,
                'url' => route('capture.criterion', [$group, $project, $product, $c]),
                'active' => $c->is($criterion),
            ]),
            'term' => $project->term,
        ]);
    }

    public function aspect(Group $group, TermAspect $termAspect): View|RedirectResponse
    {
        if ($termAspect->isFromProjects()) {
            return redirect()->route('campos.show', [$group, $termAspect->campo]);
        }

        $book = TermBook::for($group, $termAspect->term);

        return view('capture', [
            ...$this->campoContext($group, $book, $termAspect->campo, "aspect:{$termAspect->id}"),
            'kind' => 'aspect',
            'unit' => $termAspect,
            'unitKey' => "aspect:{$termAspect->id}",
            'title' => $termAspect->name,
            'subtitle' => Campos::short($termAspect->campo).' · vale '.TermBook::fmt($termAspect->weight).'%',
            'missing' => $book->aspectMissing($termAspect),
            'value' => fn ($s) => $book->aspectScore($s->id, $termAspect->id),
            'term' => $termAspect->term,
        ]);
    }

    public function subject(Group $group, Subject $subject): View
    {
        $book = TermBook::for($group);

        return view('capture', [
            ...$this->campoContext($group, $book, $subject->campo, "subject:{$subject->id}"),
            'kind' => 'subject',
            'unit' => $subject,
            'unitKey' => "subject:{$subject->id}",
            'title' => $subject->name,
            'subtitle' => Campos::short($subject->campo).' · promedia con el campo',
            'missing' => $book->subjectMissing($subject),
            'value' => fn ($s) => $book->subjectScore($s->id, $subject->id),
            'term' => $book->term,
        ]);
    }

    /** Pestañas para saltar entre lo capturable del campo (aspectos directos y materias). */
    private function campoContext(Group $group, TermBook $book, string $campo, string $activeKey): array
    {
        $tabs = $book->aspectsFor($campo)->reject->isFromProjects()->map(fn ($a) => [
            'label' => $a->name, 'url' => route('capture.aspect', [$group, $a]), 'active' => $activeKey === "aspect:{$a->id}",
        ])->merge($book->subjectsFor($campo)->map(fn ($s) => [
            'label' => $s->name, 'url' => route('capture.subject', [$group, $s]), 'active' => $activeKey === "subject:{$s->id}",
        ]))->values();

        return [
            'group' => $group,
            'book' => $book,
            'campo' => $campo,
            'mode' => 'score',
            'descriptors' => [],
            'back' => [route('campos.show', [$group, $campo]), Campos::name($campo)],
            'tabs' => $tabs,
        ];
    }
}
