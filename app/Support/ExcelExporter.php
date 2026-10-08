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
 * Libro de Excel de un trimestre:
 *   Resumen      → final de cada campo formativo + promedio y nivel
 *   un campo     → aspectos (con %), base, materias, final y la MATRIZ de niveles
 *                  (la final se coloca en la columna de su nivel: 8 → "En proceso")
 *   un proyecto  → encabezado (campo, PDA) + producto → criterio → 4 columnas de nivel
 *   Instrumentos → cada criterio con su descriptor por nivel
 *   Pendientes   → lo que falta capturar
 * Los valores son los calculados por TermBook (misma regla que la pantalla).
 */
class ExcelExporter
{
    private const HEADER_FILL = '4C1D95';
    private const SUBHEADER_FILL = 'EDE7FD';
    private const MISSING_FILL = 'FFF3B0';

    private Spreadsheet $wb;

    /** @var array<string, true> */
    private array $usedTitles = [];

    private function __construct(private readonly TermBook $book)
    {
        $this->wb = new Spreadsheet;
        $this->wb->getProperties()->setCreator(config('app.name'));
        $this->wb->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    }

    public static function forTerm(Group $group, int $term): Spreadsheet
    {
        $self = new self(TermBook::for($group, $term));

        $self->summarySheet($self->wb->getActiveSheet());
        foreach (Campos::keys() as $campo) {
            $self->campoSheet($self->wb->createSheet(), $campo);
        }
        foreach ($self->book->projects as $project) {
            $self->projectSheet($self->wb->createSheet(), $project);
        }
        $self->instrumentsSheet($self->wb->createSheet());
        $self->pendingSheet($self->wb->createSheet());
        $self->wb->setActiveSheetIndex(0);

        return $self->wb;
    }

    // ------------------------------------------------------------------ hojas

    private function summarySheet(Worksheet $sheet): void
    {
        $sheet->setTitle($this->title('Resumen T'.$this->book->term));
        $first = $this->header($sheet, 'Resumen del Trimestre '.$this->book->term);

        $cols = ['N.L.', 'Alumno'];
        foreach (Campos::keys() as $c) {
            $cols[] = Campos::name($c);
        }
        $cols = [...$cols, 'Promedio', 'Nivel', 'Pendientes'];
        $sheet->fromArray($cols, null, "A{$first}");
        $last = Coordinate::stringFromColumnIndex(count($cols));
        $this->styleHeader($sheet, "A{$first}:{$last}{$first}");
        $sheet->getRowDimension($first)->setRowHeight(45);

        $r = $first + 1;
        foreach ($this->book->students as $s) {
            $sheet->setCellValue("A{$r}", $s->list_number);
            $sheet->setCellValue("B{$r}", $s->name);
            $col = 3;
            foreach (Campos::keys() as $c) {
                $this->levelValue($sheet, $col++, $r, $this->book->campoFinal($c, $s), $this->book->campoComplete($c, $s));
            }
            $avg = $this->book->generalAverage($s);
            $this->levelValue($sheet, $col++, $r, $avg, true);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++).$r, Level::label(Level::of($avg)));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$r, $this->book->missingFor($s) ?: '');
            $r++;
        }

        $this->finishTable($sheet, $first, $r - 1, $last, [34, 14]);
        $this->note($sheet, $r + 1, 'Amarillo = el campo tiene calificaciones pendientes para ese alumno (la nota es provisional). Color del número = nivel de logro.');
    }

    private function campoSheet(Worksheet $sheet, string $campo): void
    {
        $sheet->setTitle($this->title(Campos::short($campo).' T'.$this->book->term));
        $first = $this->header($sheet, Campos::name($campo).' · Trimestre '.$this->book->term);
        $aspects = $this->book->aspectsFor($campo);
        $subjects = $this->book->subjectsFor($campo);

        if ($aspects->isEmpty()) {
            $sheet->setCellValue("A{$first}", 'Este campo no tiene aspectos definidos en el trimestre.');

            return;
        }

        $cols = ['N.L.', 'Alumno'];
        foreach ($aspects as $a) {
            $cols[] = $a->name.' ('.TermBook::fmt($a->weight).'%)'.($a->isFromProjects() ? ' · proyectos' : '');
        }
        $cols[] = 'Base del campo';
        foreach ($subjects as $sub) {
            $cols[] = $sub->name;
        }
        $cols[] = 'Final';
        foreach (Level::LEVELS as $l) {
            $cols[] = $l['label'];
        }
        $cols[] = 'Estado';
        $sheet->fromArray($cols, null, "A{$first}");
        $last = Coordinate::stringFromColumnIndex(count($cols));
        $this->styleHeader($sheet, "A{$first}:{$last}{$first}");
        $levelStart = count($cols) - 4; // índice 1-based de "Logrado"
        $this->colorLevelHeaders($sheet, $levelStart, $first);
        $sheet->getRowDimension($first)->setRowHeight(45);

        $r = $first + 1;
        foreach ($this->book->students as $s) {
            $sheet->setCellValue("A{$r}", $s->list_number);
            $sheet->setCellValue("B{$r}", $s->name);
            $col = 3;
            foreach ($aspects as $a) {
                $v = $this->book->aspectValue($a, $s);
                $this->levelValue($sheet, $col++, $r, $v, $v !== null);
            }
            $this->levelValue($sheet, $col++, $r, $this->book->campoBase($campo, $s), true);
            foreach ($subjects as $sub) {
                $v = $this->book->subjectScore($s->id, $sub->id);
                $this->levelValue($sheet, $col++, $r, $v, $v !== null);
            }
            $final = $this->book->campoFinal($campo, $s);
            $complete = $this->book->campoComplete($campo, $s);
            $this->levelValue($sheet, $col++, $r, $final, $complete);
            $this->levelMatrix($sheet, $col, $r, $final);
            $col += 4;
            $missing = $this->book->campoMissing($campo, $s);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$r, $missing ? "Faltan {$missing}" : 'Completo');
            $r++;
        }

        $this->finishTable($sheet, $first, $r - 1, $last, [34]);
        $this->note($sheet, $r + 1, 'Base = aspectos × su porcentaje. Final = promedio en partes iguales de la base y las materias del campo. Logrado 10 · Satisfactorio 9 · En proceso 8/7 · Requiere apoyo 6.');
    }

    private function projectSheet(Worksheet $sheet, Project $project): void
    {
        $sheet->setTitle($this->title('P · '.$project->name));
        $sheet->setCellValue('A1', $project->name)->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info = [
            ['Grupo', $this->groupLine()],
            ['Trimestre', $project->term],
            ['Campo formativo', Campos::name($project->campo)],
            ['Suma a', collect($project->campos())->map(fn ($c) => Campos::name($c))->implode(', ')],
            ['Fecha', $project->due_date?->format('d/m/Y') ?? ''],
        ];
        foreach ($project->pdas ?? [] as $i => $pda) {
            $info[] = ['PDA '.($i + 1), $pda];
        }
        $row = 2;
        foreach ($info as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label)->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("C{$row}", $value);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(false);
            $row++;
        }

        // Tres filas de encabezado: producto · criterio · nivel
        $h1 = $row + 1;
        $h2 = $h1 + 1;
        $h3 = $h2 + 1;
        $sheet->setCellValue("A{$h3}", 'N.L.');
        $sheet->setCellValue("B{$h3}", 'Alumno');
        $col = 3;
        $layout = []; // [product, criterion|null(promedio), col]
        foreach ($project->products as $product) {
            $start = $col;
            foreach ($product->criteria as $i => $criterion) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$h2, ($i + 1).'. '.$criterion->description);
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($col).$h2.':'.Coordinate::stringFromColumnIndex($col + 3).$h2);
                foreach (array_keys(Level::LEVELS) as $k => $lvl) {
                    $cell = Coordinate::stringFromColumnIndex($col + $k).$h3;
                    $sheet->setCellValue($cell, Level::label($lvl));
                    if ($text = $criterion->{Level::column($lvl)}) {
                        $sheet->getComment($cell)->getText()->createTextRun($text);
                        $sheet->getComment($cell)->setWidth('260pt')->setHeight('90pt');
                    }
                }
                $this->colorLevelHeaders($sheet, $col, $h3);
                $layout[] = [$product, $criterion, $col];
                $col += 4;
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$h2, 'Promedio');
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($col).$h2.':'.Coordinate::stringFromColumnIndex($col).$h3);
            $layout[] = [$product, null, $col];
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($start).$h1,
                $product->name.' · '.Campos::short($product->campo).($product->instrument ? ' · '.$product->instrument : ''));
            if ($col > $start) {
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($start).$h1.':'.Coordinate::stringFromColumnIndex($col).$h1);
            }
            $col++;
        }
        $last = Coordinate::stringFromColumnIndex(max($col - 1, 2));
        $sheet->mergeCells("A{$h1}:A{$h2}");
        $sheet->mergeCells("B{$h1}:B{$h2}");
        $this->styleHeader($sheet, "A{$h1}:{$last}{$h1}");
        $this->styleHeader($sheet, "A{$h2}:{$last}{$h2}", self::SUBHEADER_FILL, '1E1533');
        $sheet->getStyle("A{$h3}:B{$h3}")->getFont()->setBold(true);
        $sheet->getRowDimension($h2)->setRowHeight(48);

        $r = $h3 + 1;
        foreach ($this->book->students as $s) {
            $sheet->setCellValue("A{$r}", $s->list_number);
            $sheet->setCellValue("B{$r}", $s->name);
            foreach ($layout as [$product, $criterion, $c]) {
                if ($criterion) {
                    $this->levelMatrix($sheet, $c, $r, $this->book->criterionScore($s->id, $criterion->id));
                } else {
                    $this->levelValue($sheet, $c, $r, $this->book->productScore($product, $s), $this->book->productMissing($product, $s) === 0);
                }
            }
            $r++;
        }

        $this->finishTable($sheet, $h1, $r - 1, $last, [34], $h3 + 1);
        foreach (range(3, max(3, $col - 1)) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(12);
        }
        $sheet->freezePane('C'.($h3 + 1));
        $this->note($sheet, $r + 1, 'La calificación aparece en la columna de su nivel. Los comentarios de los encabezados de nivel traen el descriptor de la rúbrica (también en la hoja Instrumentos).');
    }

    private function instrumentsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle($this->title('Instrumentos'));
        $cols = ['Proyecto', 'Producto', 'Campo', 'Instrumento', 'Criterio', ...array_map(fn ($l) => $l['label'], Level::LEVELS)];
        $sheet->fromArray($cols, null, 'A1');
        $this->styleHeader($sheet, 'A1:I1');
        $this->colorLevelHeaders($sheet, 6, 1);

        $r = 2;
        foreach ($this->book->projects as $project) {
            foreach ($project->products as $product) {
                foreach ($product->criteria as $c) {
                    $sheet->fromArray([
                        $project->name, $product->name, Campos::name($product->campo), $product->instrument, $c->description,
                        ...array_map(fn ($lvl) => $c->{Level::column($lvl)}, array_keys(Level::LEVELS)),
                    ], null, "A{$r}");
                    $r++;
                }
            }
        }
        foreach (['A' => 26, 'B' => 24, 'C' => 22, 'D' => 16, 'E' => 34, 'F' => 34, 'G' => 34, 'H' => 34, 'I' => 34] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
        $sheet->getStyle('A1:I'.max(1, $r - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('A2');
    }

    private function pendingSheet(Worksheet $sheet): void
    {
        $sheet->setTitle($this->title('Pendientes'));
        $sheet->fromArray(['Campo', 'Tipo', 'Qué falta', 'N.L.', 'Alumno'], null, 'A1');
        $this->styleHeader($sheet, 'A1:E1');

        $rows = [];
        foreach ($this->book->students as $s) {
            foreach (Campos::keys() as $campo) {
                foreach ($this->book->productsFor($campo) as $product) {
                    foreach ($product->criteria as $c) {
                        if ($this->book->criterionScore($s->id, $c->id) === null) {
                            $rows[] = [Campos::short($campo), 'Proyecto', $product->project->name.' · '.$product->name.' · '.$c->description, $s->list_number, $s->name];
                        }
                    }
                }
                foreach ($this->book->aspectsFor($campo)->reject->isFromProjects() as $a) {
                    if ($this->book->aspectScore($s->id, $a->id) === null) {
                        $rows[] = [Campos::short($campo), 'Aspecto', $a->name, $s->list_number, $s->name];
                    }
                }
                foreach ($this->book->subjectsFor($campo) as $sub) {
                    if ($this->book->subjectScore($s->id, $sub->id) === null) {
                        $rows[] = [Campos::short($campo), 'Materia', $sub->name, $s->list_number, $s->name];
                    }
                }
            }
        }

        $rows
            ? $sheet->fromArray($rows, null, 'A2')
            : $sheet->setCellValue('A2', 'Sin pendientes: todo está calificado.');

        foreach (['A' => 16, 'B' => 12, 'C' => 60, 'D' => 6, 'E' => 34] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
        $sheet->freezePane('A2');
    }

    // ------------------------------------------------------------------ piezas

    /** Encabezado de escuela; devuelve la fila donde empieza la tabla. */
    private function header(Worksheet $sheet, string $title): int
    {
        $sheet->setCellValue('A1', $title)->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $this->group()->school_name ?: '');
        $sheet->setCellValue('A3', $this->groupLine());
        $sheet->getStyle('A2:A3')->getFont()->setSize(10);

        return 5;
    }

    private function group(): Group
    {
        return $this->book->group;
    }

    /** "Grupo 4° C · Matutino · CCT … · Zona … · 2026-2027" */
    private function groupLine(): string
    {
        $g = $this->group();

        return implode('   ·   ', array_filter([
            'Grupo '.$g->label(), $g->shiftLabel(),
            $g->school_cct ? 'CCT '.$g->school_cct : null,
            $g->school_zone ? 'Zona '.$g->school_zone : null,
            $g->school_year,
        ]));
    }

    /** Número con color de su nivel; amarillo si es provisional/pendiente. */
    private function levelValue(Worksheet $sheet, int $col, int $row, ?float $value, bool $complete): void
    {
        $cell = Coordinate::stringFromColumnIndex($col).$row;
        if ($value === null) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MISSING_FILL);

            return;
        }
        $sheet->setCellValue($cell, $value);
        $style = $sheet->getStyle($cell);
        $style->getNumberFormat()->setFormatCode('0.0#');
        $style->getFont()->setBold(true)->getColor()->setRGB(Level::LEVELS[Level::of($value)]['color']);
        if (! $complete) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MISSING_FILL);
        }
    }

    /** 4 columnas Logrado · Satisfactorio · En proceso · Requiere apoyo: el valor cae en la de su nivel. */
    private function levelMatrix(Worksheet $sheet, int $col, int $row, ?float $value): void
    {
        $level = Level::of($value);
        foreach (array_keys(Level::LEVELS) as $k => $lvl) {
            $cell = Coordinate::stringFromColumnIndex($col + $k).$row;
            if ($value === null) {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MISSING_FILL);
            } elseif ($lvl === $level) {
                $sheet->setCellValue($cell, $value);
                $style = $sheet->getStyle($cell);
                $style->getFont()->setBold(true)->getColor()->setRGB(Level::LEVELS[$lvl]['color']);
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(Level::LEVELS[$lvl]['soft']);
            }
        }
    }

    private function colorLevelHeaders(Worksheet $sheet, int $startCol, int $row): void
    {
        foreach (array_values(Level::LEVELS) as $k => $l) {
            $style = $sheet->getStyle(Coordinate::stringFromColumnIndex($startCol + $k).$row);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($l['color']);
            $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        }
    }

    /** @param list<int> $widths anchos de B, C… a partir de B */
    private function finishTable(Worksheet $sheet, int $headerRow, int $lastRow, string $lastCol, array $widths, ?int $dataStart = null): void
    {
        $lastRow = max($lastRow, $headerRow);
        $dataStart ??= $headerRow + 1;
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9D2EA');
        $sheet->getStyle("C{$dataStart}:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$headerRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(6);
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $i))->setWidth($w);
        }
        $end = Coordinate::columnIndexFromString($lastCol);
        for ($c = 2 + count($widths); $c <= $end; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(14);
        }
        $sheet->freezePane('C'.$dataStart);
    }

    private function styleHeader(Worksheet $sheet, string $range, string $fill = self::HEADER_FILL, string $font = 'FFFFFF'): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB($font);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function note(Worksheet $sheet, int $row, string $text): void
    {
        $sheet->setCellValue("A{$row}", $text);
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9);
    }

    /** Excel: máx. 31 caracteres, sin []:*?/\ y sin repetir. */
    private function title(string $name): string
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
