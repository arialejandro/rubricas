<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Calificación (6–10) de un alumno en una aplicación de una habilidad (modos "nivel" y "medición").
 * `measure` = la medición si la habilidad es medible. skill_id/term se guardan también para consultar rápido.
 */
#[Fillable(['skill_id', 'skill_application_id', 'student_id', 'term', 'score', 'measure'])]
class SkillScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'integer', 'term' => 'integer', 'measure' => 'float'];
    }
}
