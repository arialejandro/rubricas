<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "/" lleva al tablero del turno activo (el último grupo visitado) o al alta del primero. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $groups = $request->user()->groups()->get();

        if ($groups->isEmpty()) {
            return redirect()->route('grupos.create');
        }

        $group = $groups->firstWhere('id', $request->session()->get('group_id')) ?? $groups->first();

        return redirect()->route('grupos.show', $group);
    }
}
