<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\Campos;
use App\Support\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel de un trimestre (?trimestre=N; por omisión, el trimestre activo del grupo). */
class ExportController extends Controller
{
    public function __invoke(Request $request, Group $group): StreamedResponse
    {
        $term = (int) $request->query('trimestre', $group->term());
        abort_unless(in_array($term, Campos::TERMS, true), 404);

        $book = ExcelExporter::forTerm($group, $term);
        $filename = Str::slug("calificaciones {$group->label()} {$group->shift} trimestre {$term}").'-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
