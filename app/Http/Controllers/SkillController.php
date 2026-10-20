<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Skill;
use App\Models\SkillApplication;
use App\Support\Campos;
use App\Support\TermBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Habilidad prioritaria en el trimestre: tabla de AVANCE (alumno × aplicación + promedio, que es
 * lo que cuenta para el campo), alta/edición de aplicaciones y la pantalla de captura de cada una.
 */
class SkillController extends Controller
{
    public function show(Group $group, Skill $skill): View
    {
        $book = TermBook::for($group);
        $skill = $book->skills->firstWhere('id', $skill->id);
        $apps = $book->applicationsFor($skill);

        return view('skills.show', compact('group', 'book', 'skill', 'apps'));
    }

    public function storeApplication(Request $request, Group $group, Skill $skill): RedirectResponse
    {
        $data = $this->validated($request);
        $app = $skill->applications()->create([...$data, 'term' => $group->term()]);

        return redirect()->route('skills.capture', [$group, $skill, $app]);
    }

    public function updateApplication(Request $request, Group $group, Skill $skill, SkillApplication $application): RedirectResponse
    {
        $application->update($this->validated($request));

        return back()->with('status', 'Aplicación actualizada.');
    }

    public function destroyApplication(Group $group, Skill $skill, SkillApplication $application): RedirectResponse
    {
        $application->delete();

        return redirect()->route('skills.show', [$group, $skill])->with('status', 'Aplicación eliminada con sus calificaciones.');
    }

    /** Captura de una aplicación: lista (nivel o medición) o matriz de criterios. */
    public function capture(Group $group, Skill $skill, SkillApplication $application): View
    {
        $book = TermBook::for($group, $application->term);
        $skill = $book->skills->firstWhere('id', $skill->id);
        $apps = $book->applicationsFor($skill);
        $number = $apps->search(fn ($a) => $a->is($application)) + 1;
        $tabs = $apps->values()->map(fn ($a, $i) => [
            'label' => $a->title($i + 1),
            'url' => route('skills.capture', [$group, $skill, $a]),
            'active' => $a->is($application),
        ]);

        if ($skill->isByCriteria()) {
            return view('skills.capture-criteria', compact('group', 'book', 'skill', 'application', 'number', 'tabs'));
        }

        return view('capture', [
            'group' => $group,
            'book' => $book,
            'campo' => $skill->campo,
            'mode' => $skill->keypadMode(),
            'kind' => 'skill',
            'unit' => $application,
            'skill' => $skill,
            'unitKey' => "app:{$application->id}",
            'title' => $skill->name,
            'subtitle' => $application->title($number).($skill->isMeasured() ? ' · se mide en '.$skill->measure_unit : ''),
            'missing' => $book->appStudentsMissing($skill, $application->id),
            'value' => fn ($s) => $book->appScore($skill, $application->id, $s->id),
            'measure' => fn ($s) => $book->appMeasure($s->id, $application->id),
            'descriptors' => $skill->descriptors(),
            'labels' => $skill->labels(),
            'keypad' => $skill->keypadConfig(),
            'back' => [route('skills.show', [$group, $skill]), 'Avance de '.$skill->name],
            'tabs' => $tabs,
            'term' => $application->term,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'applied_on' => ['nullable', 'date'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
