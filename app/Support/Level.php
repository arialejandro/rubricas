<?php

namespace App\Support;

/**
 * Niveles de logro. Un criterio de proyecto se califica con el nivel:
 * Logrado 10 · Satisfactorio 9 · En proceso 8 o 7 · Requiere apoyo 6.
 * Un promedio (campo, producto) se clasifica al nivel más cercano.
 * Color: verde (mejor) → rojo (peor); ver .lvl[data-level] en app.css.
 */
final class Level
{
    public const LEVELS = [
        'logrado' => ['label' => 'Logrado', 'scores' => [10], 'color' => '15803D', 'soft' => 'DCFCE7'],
        'satisfactorio' => ['label' => 'Satisfactorio', 'scores' => [9], 'color' => '4D7C0F', 'soft' => 'ECFCCB'],
        'proceso' => ['label' => 'En proceso', 'scores' => [8, 7], 'color' => 'B45309', 'soft' => 'FEF3C7'],
        'apoyo' => ['label' => 'Requiere apoyo', 'scores' => [6], 'color' => 'B91C1C', 'soft' => 'FEE2E2'],
    ];

    /** Calificaciones válidas para un criterio de proyecto. */
    public const CRITERION_SCORES = [10, 9, 8, 7, 6];

    public static function of(?float $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 9.5 => 'logrado',
            $score >= 8.5 => 'satisfactorio',
            $score >= 6.5 => 'proceso',
            default => 'apoyo',
        };
    }

    public static function label(?string $level): string
    {
        return self::LEVELS[$level]['label'] ?? '';
    }

    /** Clave del descriptor en la tabla criteria ("level_logrado"…). */
    public static function column(string $level): string
    {
        return 'level_'.$level;
    }
}
