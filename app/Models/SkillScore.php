<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Calificación (6–10) de una habilidad en un trimestre; `measure` = la medición si la habilidad es medible. */
#[Fillable(['skill_id', 'student_id', 'term', 'score', 'measure'])]
class SkillScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'integer', 'term' => 'integer', 'measure' => 'float'];
    }
}
