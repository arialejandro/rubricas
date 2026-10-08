<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\Campos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cambia el trimestre activo del grupo (se guarda en el grupo: igual en iPad y computadora). */
class TermController extends Controller
{
    public function __invoke(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate(['term' => ['required', 'integer', Rule::in(Campos::TERMS)]]);
        $group->update(['current_term' => $data['term']]);

        // Las páginas de un proyecto/aspecto concreto pertenecen a otro trimestre: se vuelve al inicio de la sección.
        $back = url()->previous();
        $section = collect(['campos', 'proyectos', 'alumnos'])->first(fn ($s) => str_contains($back, "/grupos/{$group->id}/{$s}"));

        return redirect()->to(match ($section) {
            'campos' => route('campos.index', $group),
            'proyectos' => route('projects.index', $group),
            'alumnos' => $back,
            default => route('grupos.show', $group),
        });
    }
}
