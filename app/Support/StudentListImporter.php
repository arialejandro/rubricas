<?php

namespace App\Support;

use App\Models\Group;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Convierte una lista de alumnos (Excel/CSV o texto pegado) en renglones [número, nombre]
 * y la aplica al grupo sin duplicar: si el nombre ya existe solo se actualiza su N.L.
 *
 * Formatos aceptados del Excel (la primera hoja):
 *  - Con encabezados: "N.L." / "No." / "Número" + "Nombre" / "Alumno".
 *  - Apellidos separados: "Apellido paterno" + "Apellido materno" + "Nombre(s)" → se unen.
 *  - Sin encabezados: columna A = N.L. (si es número) y B = nombre; o solo nombres en A.
 */
class StudentListImporter
{
    public const MAX_ROWS = 80;

    /** @return list<array{number:?int, name:string}> */
    public static function fromUpload(UploadedFile $file): array
    {
        $type = match (strtolower($file->getClientOriginalExtension())) {
            'xls' => 'Xls',
            'csv', 'txt' => 'Csv',
            default => 'Xlsx',
        };

        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true);
        $rows = $reader->load($file->getRealPath())->getSheet(0)->toArray(null, true, false, false);

        return self::fromRows($rows);
    }

    /** @return list<array{number:?int, name:string}> */
    public static function fromText(string $text): array
    {
        // Cada renglón se interpreta solo: pegado desde Excel llega con tabuladores
        // ("12<TAB>López<TAB>Ana"); a mano, "12 Ana López", "12. Ana" o solo el nombre.
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim(str_replace("\t", ' ', $line));
            $number = null;
            if (preg_match('/^(\d{1,3})[\s.\-)]+(.+)$/u', $line, $m)) {
                $number = (int) $m[1];
                $line = $m[2];
            }

            $name = self::cleanName($line);
            $isHeader = preg_match('/^(n\.?\s*l\.?\s+)?(nombre|alumno)/iu', $name) && $number === null;
            if ($name === '' || is_numeric($name) || $isHeader) {
                continue;
            }

            $out[] = ['number' => $number ?: null, 'name' => $name];
        }

        return array_slice($out, 0, self::MAX_ROWS);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private static function fromRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => collect($r)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()));
        if ($rows === []) {
            return [];
        }

        $map = self::detectHeader($rows[0]);
        if ($map !== null) {
            array_shift($rows);
        } else {
            $first = array_values(array_filter($rows[0], fn ($v) => trim((string) $v) !== ''));
            $map = count($first) >= 2 && is_numeric(trim((string) $first[0]))
                ? ['number' => self::firstFilledIndex($rows[0]), 'name' => [self::firstFilledIndex($rows[0]) + 1]]
                : ['number' => null, 'name' => [self::firstFilledIndex($rows[0])]];
        }

        $out = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $name = collect($map['name'])->map(fn ($i) => trim((string) ($row[$i] ?? '')))->filter()->implode(' ');
            $name = self::cleanName($name);
            if ($name === '' || is_numeric($name)) {
                continue;
            }

            $number = $map['number'] !== null ? trim((string) ($row[$map['number']] ?? '')) : '';
            $out[] = [
                'number' => ctype_digit($number) && (int) $number > 0 && (int) $number < 1000 ? (int) $number : null,
                'name' => $name,
            ];
        }

        return $out;
    }

    /** @return array{number:?int, name:list<int>}|null */
    private static function detectHeader(array $row): ?array
    {
        $number = null;
        $name = null;
        $paterno = null;
        $materno = null;

        foreach ($row as $i => $cell) {
            $h = Str::of((string) $cell)->ascii()->lower()->replaceMatches('/[^a-z]/', '')->toString();
            if ($h === '') {
                continue;
            }
            if (in_array($h, ['nl', 'no', 'num', 'numero', 'numerodelista', 'n', 'lista', 'nlista'], true)) {
                $number ??= $i;
            } elseif (str_contains($h, 'paterno')) {
                $paterno = $i;
            } elseif (str_contains($h, 'materno')) {
                $materno = $i;
            } elseif (str_contains($h, 'nombre') || str_contains($h, 'alumno')) {
                $name ??= $i;
            }
        }

        if ($name === null && $paterno === null) {
            return null;
        }

        // Orden de lista oficial: apellidos primero, luego nombre(s).
        $parts = array_values(array_filter([$paterno, $materno, $name], fn ($v) => $v !== null));

        return ['number' => $number, 'name' => $parts];
    }

    private static function firstFilledIndex(array $row): int
    {
        foreach ($row as $i => $v) {
            if (trim((string) $v) !== '') {
                return $i;
            }
        }

        return 0;
    }

    /** "  LÓPEZ  PÉREZ ANA " → "López Pérez Ana". Si ya viene en mayúsculas/minúsculas mixtas se respeta. */
    public static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name !== '' && mb_strtoupper($name) === $name) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
        }

        return Str::limit($name, 250, '');
    }

    /**
     * @param  list<array{number:?int, name:string}>  $rows
     * @return array{added:int, updated:int, unchanged:int}
     */
    public static function apply(Group $group, array $rows): array
    {
        $key = fn (string $n) => Str::of($n)->ascii()->lower()->squish()->toString();
        $existing = $group->students()->get()->keyBy(fn ($s) => $key($s->name));
        $report = ['added' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($rows as $row) {
            $k = $key($row['name']);
            $student = $existing->get($k);

            if ($student === null) {
                $existing[$k] = $group->students()->create(['name' => $row['name'], 'list_number' => $row['number']]);
                $report['added']++;
            } elseif ($row['number'] !== null && $student->list_number !== $row['number']) {
                $student->update(['list_number' => $row['number']]);
                $report['updated']++;
            } else {
                $report['unchanged']++;
            }
        }

        return $report;
    }

    /** Plantilla .xlsx con los encabezados que se esperan. */
    public static function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Alumnos');
        $sheet->fromArray([['N.L.', 'Nombre'], [1, 'Apellido Apellido Nombre'], [2, '']], null, 'A1');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(42);

        return $book;
    }
}
