<?php

namespace App\Models;

use App\Support\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Criterio de una habilidad "por criterios", con el descriptor de cada nivel. */
#[Fillable(['description', 'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo', 'position'])]
class SkillCriterion extends Model
{
    protected $table = 'skill_criteria';

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SkillCriterionScore::class);
    }

    /** @return array<string, string> */
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
