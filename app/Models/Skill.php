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
    'campo', 'name', 'measure_unit', 'lower_is_better', 'min_7', 'min_8', 'min_9', 'min_10', 'level_labels',
    'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo', 'position',
])]
class Skill extends Model
{
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

    public function isMeasured(): bool
    {
        return filled($this->measure_unit);
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
