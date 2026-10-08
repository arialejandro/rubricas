<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'list_number', 'active'])]
class Student extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** ¿Tiene alguna calificación capturada? (entonces no se borra: se da de baja). */
    public function hasScores(): bool
    {
        return CriterionScore::where('student_id', $this->id)->exists()
            || AspectScore::where('student_id', $this->id)->exists()
            || SubjectScore::where('student_id', $this->id)->exists();
    }
}
