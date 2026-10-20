<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una aplicación de una habilidad en un trimestre (ej. Multiplicación, 19 de octubre).
 * La habilidad vale el promedio de sus aplicaciones; el historial muestra el avance.
 */
#[Fillable(['term', 'applied_on', 'label'])]
class SkillApplication extends Model
{
    protected function casts(): array
    {
        return ['applied_on' => 'date', 'term' => 'integer'];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SkillScore::class);
    }

    public function criterionScores(): HasMany
    {
        return $this->hasMany(SkillCriterionScore::class);
    }

    /** "Aplicación 2 · 19 oct" (número dentro de su habilidad y trimestre). */
    public function title(int $number): string
    {
        return trim(($this->label ?: 'Aplicación '.$number).($this->applied_on ? ' · '.$this->applied_on->translatedFormat('j M') : ''));
    }
}
