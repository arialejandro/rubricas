<?php

namespace App\Models;

use App\Support\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Habilidad prioritaria (Lenguajes/Saberes): se califica por trimestre con nivel 6–10 y suma
 * al campo mediante el aspecto de tipo "skills".
 *
 * Dos formas de calificarla:
 *  - Por nivel: la maestra elige el nivel (con descriptores opcionales).
 *  - Medible (measure_unit no vacío): captura la medición (p. ej. 103 palabras por minuto) y la
 *    calificación sale de sus rangos: min_7…min_10 = desde cuánto vale 7, 8, 9 y 10; debajo de
 *    min_7 vale 6. Con lower_is_better los rangos son "hasta" (p. ej. número de errores).
 */
#[Fillable([
    'campo', 'name', 'grading', 'measure_unit', 'lower_is_better', 'min_7', 'min_8', 'min_9', 'min_10', 'level_labels',
    'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo', 'position',
])]
class Skill extends Model
{
    /** Cómo se califica la habilidad. */
    public const GRADINGS = ['level' => 'Elijo el nivel', 'measure' => 'Mido y se calcula', 'criteria' => 'Por criterios'];

    /** Calificación → nivel, en el orden en que se evalúan los cortes. */
    public const THRESHOLDS = ['min_10' => 10, 'min_9' => 9, 'min_8' => 8, 'min_7' => 7];

    protected function casts(): array
    {
        return [
            'lower_is_better' => 'boolean',
            'min_7' => 'float', 'min_8' => 'float', 'min_9' => 'float', 'min_10' => 'float',
            'level_labels' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SkillScore::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(SkillCriterion::class)->orderBy('position')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SkillApplication::class)->orderByRaw('applied_on IS NULL, applied_on')->orderBy('id');
    }

    public function isMeasured(): bool
    {
        return $this->grading === 'measure' && filled($this->measure_unit);
    }

    public function isByCriteria(): bool
    {
        return $this->grading === 'criteria';
    }

    /** Modo del teclado para capturar una aplicación (en "por criterios" se califica cada criterio por nivel). */
    public function keypadMode(): string
    {
        return $this->isMeasured() ? 'measure' : 'levels';
    }

    /**
     * Corte entre 7 y 8 dentro de "En proceso": la mitad entre su valor más bajo y el más alto.
     * Ej. En proceso 85–99 → 8 desde 92 (85–91 = 7, 92–99 = 8). Con "menos es mejor", al revés.
     */
    public static function splitProceso(float $min7, float $min9, bool $lower): float
    {
        $integers = floor($min7) == $min7 && floor($min9) == $min9;
        if ($lower) {
            // "hasta": En proceso va de (min9, min7]; 8 = la mitad más baja.
            return $integers ? floor((($min9 + 1) + $min7) / 2) : ($min9 + $min7) / 2;
        }

        // "desde": En proceso va de [min7, min9); 8 = la mitad más alta.
        return $integers ? ceil(($min7 + ($min9 - 1)) / 2) : ($min7 + $min9) / 2;
    }

    /** Calificación de una aplicación "por criterios": promedio redondeado al nivel (6–10). */
    public static function scoreFromCriteria(float $average): int
    {
        return (int) max(6, min(10, round($average)));
    }

    /** Calificación 6–10 que corresponde a una medición según los rangos de la maestra. */
    public function scoreFor(float $measure): int
    {
        foreach (self::THRESHOLDS as $column => $score) {
            $limit = $this->{$column};
            if ($limit !== null && ($this->lower_is_better ? $measure <= $limit : $measure >= $limit)) {
                return $score;
            }
        }

        return 6;
    }

    /** Nombre del nivel para esta habilidad (propio si la maestra lo definió). */
    public function levelLabel(?string $level): string
    {
        return $this->level_labels[$level] ?? Level::label($level);
    }

    /** @return array<string, string> nivel => nombre */
    public function labels(): array
    {
        return collect(array_keys(Level::LEVELS))->mapWithKeys(fn ($l) => [$l => $this->levelLabel($l)])->all();
    }

    /**
     * Descriptor de cada nivel: el que escribió la maestra o, si la habilidad es medible,
     * el rango calculado ("100 a 114 palabras por minuto").
     *
     * @return array<string, string>
     */
    public function descriptors(): array
    {
        $ranges = $this->isMeasured() ? $this->ranges() : [];
        $out = [];
        foreach (array_keys(Level::LEVELS) as $level) {
            $text = trim(implode(' · ', array_filter([$ranges[$level] ?? null, $this->{Level::column($level)}])));
            if ($text !== '') {
                $out[$level] = $text;
            }
        }

        return $out;
    }

    /** @return array<string, string> nivel => rango legible */
    public function ranges(): array
    {
        $u = $this->measure_unit;
        $f = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        [$t7, $t8, $t9, $t10] = [$this->min_7, $this->min_8, $this->min_9, $this->min_10];

        if ($this->lower_is_better) {
            return [
                'logrado' => "{$f($t10)} o menos {$u}",
                'satisfactorio' => "hasta {$f($t9)} {$u}",
                'proceso' => "hasta {$f($t7)} {$u} (8 hasta {$f($t8)})",
                'apoyo' => "más de {$f($t7)} {$u}",
            ];
        }

        return [
            'logrado' => "{$f($t10)} o más {$u}",
            'satisfactorio' => "{$f($t9)} a menos de {$f($t10)} {$u}",
            'proceso' => "{$f($t7)} a menos de {$f($t9)} {$u} (8 desde {$f($t8)})",
            'apoyo' => "menos de {$f($t7)} {$u}",
        ];
    }

    /**
     * Tabla de equivalencias de una habilidad medible, de 6 a 10:
     * [['score' => 6, 'level' => 'apoyo', 'label' => 'Requiere apoyo', 'range' => 'menos de 85'], …].
     * Si los cortes son enteros se muestran rangos cerrados ("100 a 114").
     *
     * @return list<array{score:int, level:string, label:string, range:string}>
     */
    public function scoreBands(): array
    {
        $t = [7 => $this->min_7, 8 => $this->min_8, 9 => $this->min_9, 10 => $this->min_10];
        $f = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $int = collect($t)->every(fn ($v) => $v !== null && floor($v) == $v);

        if ($this->lower_is_better) {
            $range = [6 => 'más de '.$f($t[7]), 10 => $f($t[10]).' o menos'];
            foreach ([7, 8, 9] as $s) {
                $range[$s] = ($int ? $f($t[$s + 1] + 1) : 'más de '.$f($t[$s + 1])).' a '.$f($t[$s]);
            }
        } else {
            $range = [6 => 'menos de '.$f($t[7]), 10 => $f($t[10]).' o más'];
            foreach ([7, 8, 9] as $s) {
                $range[$s] = $f($t[$s]).' a '.($int ? $f($t[$s + 1] - 1) : 'menos de '.$f($t[$s + 1]));
            }
        }

        return array_map(fn ($s) => [
            'score' => $s,
            'level' => Level::of($s),
            'label' => $this->levelLabel(Level::of($s)),
            'range' => $range[$s],
        ], [6, 7, 8, 9, 10]);
    }

    /** Lo que el teclado necesita para calcular el nivel en vivo. */
    public function keypadConfig(): array
    {
        return [
            'descriptors' => $this->descriptors(),
            'labels' => $this->labels(),
            'unit' => $this->measure_unit,
            'lower' => $this->lower_is_better,
            'thresholds' => $this->isMeasured()
                ? ['10' => $this->min_10, '9' => $this->min_9, '8' => $this->min_8, '7' => $this->min_7]
                : null,
        ];
    }
}
