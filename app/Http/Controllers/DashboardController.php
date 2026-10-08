<?php

namespace App\Http\Controllers;

use App\Support\Gradebook;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $groups = $request->user()->groups()->withCount(['activeStudents', 'projects'])->get();

        $missing = $groups->mapWithKeys(fn ($g) => [
            $g->id => array_sum(array_column(Gradebook::summaryForGroup($g), 'missing')),
        ]);

        return view('dashboard', compact('groups', 'missing'));
    }
}
