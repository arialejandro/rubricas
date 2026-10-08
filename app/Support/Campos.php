<?php

namespace App\Support;

/**
 * Catálogo fijo de los 4 campos formativos de la NEM. Se guardan por clave ("lenguajes"…)
 * en projects/products/term_aspects/subjects; aquí viven nombre, nombre corto y color.
 */
final class Campos
{
    public const ALL = [
        'lenguajes' => ['name' => 'Lenguajes', 'short' => 'Lenguajes', 'color' => '#4f46e5'],
        'saberes' => ['name' => 'Saberes y Pensamiento Científico', 'short' => 'Saberes', 'color' => '#0e7490'],
        'etica' => ['name' => 'Ética, Naturaleza y Sociedades', 'short' => 'Ética', 'color' => '#a21caf'],
        'humano' => ['name' => 'De lo Humano y lo Comunitario', 'short' => 'De lo Humano', 'color' => '#be185d'],
    ];

    /** Materias adicionales que se crean con cada grupo (editables). */
    public const DEFAULT_SUBJECTS = [
        'lenguajes' => ['Artes', 'Inglés'],
        'humano' => ['Educación Física'],
    ];

    /** Estrategia sugerida al configurar un campo por primera vez en un trimestre. */
    public const DEFAULT_ASPECTS = [
        ['name' => 'Proyectos', 'type' => 'projects', 'weight' => 50],
        ['name' => 'Examen', 'type' => 'direct', 'weight' => 30],
        ['name' => 'Tareas y participación', 'type' => 'direct', 'weight' => 20],
    ];

    public const MAX_ASPECTS = 6;

    public const TERMS = [1, 2, 3];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function exists(?string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function name(string $key): string
    {
        return self::ALL[$key]['name'] ?? $key;
    }

    public static function short(string $key): string
    {
        return self::ALL[$key]['short'] ?? $key;
    }

    public static function color(string $key): string
    {
        return self::ALL[$key]['color'] ?? '#6d28d9';
    }
}
