<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\TermAspect;
use App\Support\Campos;
use App\Support\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descargas de Excel: el trimestre completo, un aspecto, una habilidad o una materia. */
class ExportController extends Controller
{
    /** Trimestre completo (?trimestre=N; por omisión, el activo del grupo). */
    public function __invoke(Request $request, Group $group): StreamedResponse
    {
        $term = (int) $request->query('trimestre', $group->term());
        abort_unless(in_array($term, Campos::TERMS, true), 404);

        return $this->download(ExcelExporter::forTerm($group, $term), "calificaciones {$group->label()} {$group->shift} trimestre {$term}");
    }

    public function aspect(Group $group, TermAspect $termAspect): StreamedResponse
    {
        return $this->download(ExcelExporter::forAspect($group, $termAspect),
            "{$group->label()} T{$termAspect->term} ".Campos::short($termAspect->campo)." {$termAspect->name}");
    }

    public function skill(Group $group, Skill $skill): StreamedResponse
    {
        return $this->download(ExcelExporter::forSkill($group, $skill), "{$group->label()} T{$group->term()} habilidad {$skill->name}");
    }

    public function subject(Group $group, Subject $subject): StreamedResponse
    {
        return $this->download(ExcelExporter::forSubject($group, $subject), "{$group->label()} T{$group->term()} {$subject->name}");
    }

    private function download(Spreadsheet $book, string $name): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            Str::slug($name).'-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
