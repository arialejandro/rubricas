<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Group;
use App\Models\Project;
use App\Support\Gradebook;
use Illuminate\View\View;

/** Modo captura: un aspecto a la vez, lista de alumnos con teclado numérico (pensado para iPad). */
class CaptureController extends Controller
{
    public function __invoke(Group $group, Project $project, Criterion $criterion): View
    {
        $book = Gradebook::for($project);
        $ids = $book->criteria->pluck('id')->values();
        $i = $ids->search($criterion->id);

        $prev = $i > 0 ? $book->criteria[$i - 1] : null;
        $next = $i < $ids->count() - 1 ? $book->criteria[$i + 1] : null;

        return view('projects.capture', compact('group', 'project', 'criterion', 'book', 'prev', 'next'));
    }
}
