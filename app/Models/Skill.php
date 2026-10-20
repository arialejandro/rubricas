<?php

namespace App\Models;

use App\Support\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Habilidad prioritaria (Lenguajes/Saberes): se califica por trimestre con nivel 6–10 y suma
 * al campo mediante el aspecto de tipo "skills". Descriptores de nivel opcionales.
 */
#[Fillable(['campo', 'name', 'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo', 'position'])]
class Skill extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SkillScore::class);
    }

    /** @return array<string, string> nivel => descriptor (solo los que tienen texto) */
    public function descriptors(): array
    {
        $out = [];
        foreach (array_keys(Level::LEVELS) as $level) {
            if (filled($this->{Level::column($level)})) {
                $out[$level] = $this->{Level::column($level)};
            }
        }

        return $out;
    }
}
