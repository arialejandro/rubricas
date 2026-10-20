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

    /** Campos que evalúan habilidades prioritarias (lectura, escritura, matemáticas). */
    public const SKILL_CAMPOS = ['lenguajes', 'saberes'];

    /** Habilidades prioritarias que se crean con cada grupo (editables). */
    public const DEFAULT_SKILLS = [
        'lenguajes' => ['Velocidad lectora', 'Fluidez lectora', 'Comprensión lectora', 'Producción de textos'],
        'saberes' => ['Cálculo mental', 'Resolución de problemas'],
    ];

    /** Nombres de nivel de los estándares de lectura (en vez de Logrado/Satisfactorio…). */
    public const STANDARD_LABELS = [
        'logrado' => 'Avanzado',
        'satisfactorio' => 'Estándar',
        'proceso' => 'Se acerca al estándar',
        'apoyo' => 'Requiere apoyo',
    ];

    /** Velocidad lectora: <85 = 6 · 85–99 = 7/8 · 100–114 = 9 · 115+ = 10 (ajustable por la maestra). */
    public const READING_SPEED_DEFAULTS = [
        'measure_unit' => 'palabras por minuto',
        'lower_is_better' => false,
        'min_7' => 85,
        'min_8' => 92,
        'min_9' => 100,
        'min_10' => 115,
    ];

    /** Estrategia sugerida al configurar un campo por primera vez en un trimestre. */
    public const DEFAULT_ASPECTS = [
        ['name' => 'Proyectos', 'type' => 'projects', 'weight' => 50],
        ['name' => 'Examen', 'type' => 'direct', 'weight' => 30],
        ['name' => 'Tareas y participación', 'type' => 'direct', 'weight' => 20],
    ];

    public const DEFAULT_ASPECTS_WITH_SKILLS = [
        ['name' => 'Proyectos', 'type' => 'projects', 'weight' => 40],
        ['name' => 'Habilidades prioritarias', 'type' => 'skills', 'weight' => 20],
        ['name' => 'Examen', 'type' => 'direct', 'weight' => 25],
        ['name' => 'Tareas y participación', 'type' => 'direct', 'weight' => 15],
    ];

    public static function hasSkills(string $campo): bool
    {
        return in_array($campo, self::SKILL_CAMPOS, true);
    }

    public static function defaultAspects(string $campo): array
    {
        return self::hasSkills($campo) ? self::DEFAULT_ASPECTS_WITH_SKILLS : self::DEFAULT_ASPECTS;
    }

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
