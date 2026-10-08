<?php

namespace App\Support;

use App\Models\Group;
use App\Models\Project;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Libro de Excel del grupo: hoja "Resumen" (nota final por proyecto), hoja "Pendientes"
 * y una hoja por proyecto. Las notas finales son FÓRMULAS: si la maestra corrige una
 * calificación dentro de Excel, el total se recalcula solo.
 */
class ExcelExporter
{
    private const HEADER_FILL = '1E3A5F';
    private const MISSING_FILL = 'FFF3B0';

    private const FIRST_DATA_ROW = 6;

    private Spreadsheet $book;

    /** @var array<string, true> */
    private array $usedTitles = [];

    public function __construct()
    {
        $this->book = new Spreadsheet;
        $this->book->getProperties()->setCreator(config('app.name'));
        $this->book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    }

    public static function forGroup(Group $group): Spreadsheet
    {
        $self = new self;
        $books = $group->projects()->get()->map(fn (Project $p) => Gradebook::for($p));

        $summary = $self->book->getActiveSheet();
        $pending = $self->book->createSheet();

        $refs = [];
        foreach ($books as $book) {
            $refs[$book->project->id] = $self->projectSheet($self->book->createSheet(), $book);
        }

        $self->summarySheet($summary, $group, $books, $refs);
        $self->pendingSheet($pending, $books);
        $self->book->setActiveSheetIndex(0);

        return $self->book;
    }

    public static function forProject(Project $project): Spreadsheet
    {
        $self = new self;
        $book = Gradebook::for($project);
        $self->projectSheet($self->book->getActiveSheet(), $book);
        $self->pendingSheet($self->book->createSheet(), collect([$book]));
        $self->book->setActiveSheetIndex(0);

        return $self->book;
    }

    /**
     * @return array{title:string, rows:array<int,int>, finalCol:string} dónde quedó la nota final de cada alumno
     */
    private function projectSheet(Worksheet $sheet, Gradebook $book): array
    {
        $project = $book->project;
        $title = $this->sheetTitle($project->name);
        $sheet->setTitle($title);

        $n = $book->criteria->count();
        $firstScoreCol = 3;
        $lastScoreCol = $firstScoreCol + max($n, 1) - 1;
        $pctCol = Coordinate::stringFromColumnIndex($lastScoreCol + 1);
        $finalCol = Coordinate::stringFromColumnIndex($lastScoreCol + 2);
        $statusCol = Coordinate::stringFromColumnIndex($lastScoreCol + 3);
        $firstL = Coordinate::stringFromColumnIndex($firstScoreCol);
        $lastL = Coordinate::stringFromColumnIndex($lastScoreCol);

        $sheet->setCellValue('A1', $project->name)->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', self::groupLine($project->group).($project->due_date ? '   ·   Fecha: '.$project->due_date->format('d/m/Y') : ''));
        $sheet->setCellValue('A3', 'Cada aspecto se califica de 0 a 10 y aporta (calificación ÷ 10) × su peso. Celdas amarillas = sin calificar.');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

        // Fila 4: encabezados · Fila 5: pesos (las fórmulas los leen de aquí).
        $sheet->setCellValue('A4', 'No.');
        $sheet->setCellValue('B4', 'Alumno');
        $sheet->setCellValue('B5', 'Peso %');
        foreach ($book->criteria->values() as $i => $c) {
            $col = Coordinate::stringFromColumnIndex($firstScoreCol + $i);
            $sheet->setCellValue("{$col}4", $c->name);
            $sheet->setCellValue("{$col}5", $c->weight);
            $sheet->getColumnDimension($col)->setWidth(14);
        }
        $sheet->setCellValue("{$pctCol}4", '% final');
        $sheet->setCellValue("{$finalCol}4", 'Calificación');
        $sheet->setCellValue("{$statusCol}4", 'Estado');
        $sheet->setCellValue("{$pctCol}5", "=SUM({$firstL}5:{$lastL}5)");

        $this->styleHeader($sheet, "A4:{$statusCol}4");
        $sheet->getStyle("A5:{$statusCol}5")->getFont()->setItalic(true);
        $sheet->getStyle("A5:{$statusCol}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF5');
        $sheet->getRowDimension(4)->setRowHeight(32);

        $rows = [];
        $r = self::FIRST_DATA_ROW;
        foreach ($book->students as $student) {
            $sheet->setCellValue("A{$r}", $student->list_number);
            $sheet->setCellValue("B{$r}", $student->name);

            foreach ($book->criteria->values() as $i => $c) {
                $col = Coordinate::stringFromColumnIndex($firstScoreCol + $i);
                $score = $book->score($student->id, $c->id);
                if ($score === null) {
                    $sheet->getStyle("{$col}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MISSING_FILL);
                } else {
                    $sheet->setCellValue("{$col}{$r}", $score);
                }
            }

            $range = "{$firstL}{$r}:{$lastL}{$r}";
            $sheet->setCellValue("{$pctCol}{$r}", "=SUMPRODUCT({$range},{$firstL}\$5:{$lastL}\$5)/10");
            $sheet->setCellValue("{$finalCol}{$r}", "=ROUND({$pctCol}{$r}/10,2)");
            $sheet->setCellValue("{$statusCol}{$r}", "=IF(COUNTBLANK({$range})=0,\"Completo\",\"Faltan \"&COUNTBLANK({$range}))");

            $rows[$student->id] = $r;
            $r++;
        }

        $last = max($r - 1, self::FIRST_DATA_ROW);
        $sheet->getStyle("{$firstL}".self::FIRST_DATA_ROW.":{$finalCol}{$last}")->getNumberFormat()->setFormatCode('0.0#');
        $sheet->getStyle("{$finalCol}".self::FIRST_DATA_ROW.":{$finalCol}{$last}")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$statusCol}{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C8D1DC');
        $sheet->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$firstL}5:{$statusCol}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension($pctCol)->setWidth(10);
        $sheet->getColumnDimension($finalCol)->setWidth(12);
        $sheet->getColumnDimension($statusCol)->setWidth(12);
        $sheet->freezePane('C'.self::FIRST_DATA_ROW);

        return ['title' => $title, 'rows' => $rows, 'finalCol' => $finalCol];
    }

    private function summarySheet(Worksheet $sheet, Group $group, $books, array $refs): void
    {
        $sheet->setTitle($this->sheetTitle('Resumen'));
        $sheet->setCellValue('A1', 'Resumen · '.($group->school_name ?: $group->label()));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', self::groupLine($group).'   ·   Calificación final (0–10) por proyecto. Generado el '.now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

        $sheet->setCellValue('A4', 'No.');
        $sheet->setCellValue('B4', 'Alumno');
        $col = 3;
        foreach ($books as $book) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'4', $book->project->name);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(16);
            $col++;
        }
        $avgCol = Coordinate::stringFromColumnIndex($col);
        $lastProjCol = Coordinate::stringFromColumnIndex(max($col - 1, 3));
        $sheet->setCellValue("{$avgCol}4", 'Promedio');
        $this->styleHeader($sheet, "A4:{$avgCol}4");
        $sheet->getRowDimension(4)->setRowHeight(32);

        $students = $group->activeStudents()->get();
        $r = 5;
        foreach ($students as $student) {
            $sheet->setCellValue("A{$r}", $student->list_number);
            $sheet->setCellValue("B{$r}", $student->name);
            $c = 3;
            foreach ($books as $book) {
                $ref = $refs[$book->project->id];
                $cell = Coordinate::stringFromColumnIndex($c).$r;
                if (isset($ref['rows'][$student->id])) {
                    $sheet->setCellValue($cell, "='".str_replace("'", "''", $ref['title'])."'!{$ref['finalCol']}{$ref['rows'][$student->id]}");
                }
                if (! $book->isComplete($student)) {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MISSING_FILL);
                }
                $c++;
            }
            if ($books->isNotEmpty()) {
                $sheet->setCellValue("{$avgCol}{$r}", "=ROUND(AVERAGE(C{$r}:{$lastProjCol}{$r}),2)");
            }
            $r++;
        }

        $last = max($r - 1, 5);
        $sheet->getStyle("C5:{$avgCol}{$last}")->getNumberFormat()->setFormatCode('0.0#');
        $sheet->getStyle("C5:{$avgCol}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$avgCol}5:{$avgCol}{$last}")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$avgCol}{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C8D1DC');
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension($avgCol)->setWidth(12);
        $sheet->setCellValue('A'.($last + 2), 'Amarillo = el proyecto tiene aspectos sin calificar para ese alumno (la nota es provisional).');
        $sheet->getStyle('A'.($last + 2))->getFont()->setItalic(true)->setSize(9);
        $sheet->freezePane('C5');
    }

    private function pendingSheet(Worksheet $sheet, $books): void
    {
        $sheet->setTitle($this->sheetTitle('Pendientes'));
        $sheet->fromArray(['Proyecto', 'Aspecto', 'No.', 'Alumno'], null, 'A1');
        $this->styleHeader($sheet, 'A1:D1');

        $r = 2;
        foreach ($books as $book) {
            foreach ($book->students as $student) {
                foreach ($book->missingFor($student) as $c) {
                    $sheet->fromArray([$book->project->name, $c->name, $student->list_number, $student->name], null, "A{$r}");
                    $r++;
                }
            }
        }

        if ($r === 2) {
            $sheet->setCellValue('A2', 'Sin pendientes: todos los alumnos tienen todas sus calificaciones.');
        }

        foreach (['A' => 30, 'B' => 30, 'C' => 6, 'D' => 34] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A2');
    }

    /** "Grupo 3° B · Matutino · CCT 09DPR… · Zona 015 · 2026-2027" */
    private static function groupLine(Group $group): string
    {
        return implode('   ·   ', array_filter([
            'Grupo '.$group->label(),
            $group->shiftLabel(),
            $group->school_cct ? 'CCT '.$group->school_cct : null,
            $group->school_zone ? 'Zona '.$group->school_zone : null,
            $group->school_year,
        ]));
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Excel: máx. 31 caracteres, sin []:*?/\ y sin repetir. */
    private function sheetTitle(string $name): string
    {
        $base = Str::limit(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name)) ?: 'Hoja', 28, '');
        $title = $base;
        for ($i = 2; isset($this->usedTitles[Str::lower($title)]); $i++) {
            $title = Str::limit($base, 27 - strlen((string) $i), '')." ({$i})";
        }
        $this->usedTitles[Str::lower($title)] = true;

        return $title;
    }
}
