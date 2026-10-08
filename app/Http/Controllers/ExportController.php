<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Project;
use App\Support\ExcelExporter;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function group(Group $group): StreamedResponse
    {
        return $this->download(ExcelExporter::forGroup($group), 'calificaciones '.$group->label().' '.$group->shift);
    }

    public function project(Group $group, Project $project): StreamedResponse
    {
        return $this->download(ExcelExporter::forProject($project), $group->label().' '.$project->name);
    }

    private function download(Spreadsheet $book, string $name): StreamedResponse
    {
        $filename = Str::slug($name).'-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
