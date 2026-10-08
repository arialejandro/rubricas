<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo = salón de una maestra en un turno. `name` es la letra del grupo ("B");
 * label() lo arma con el grado: "3° B".
 */
#[Fillable(['shift', 'grade', 'name', 'school_year', 'school_name', 'school_cct', 'school_zone'])]
class Group extends Model
{
    public const SHIFTS = ['matutino' => 'Matutino', 'vespertino' => 'Vespertino'];

    public const MAX_PER_TEACHER = 2;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class)->orderByRaw('list_number IS NULL, list_number')->orderBy('name');
    }

    public function activeStudents(): HasMany
    {
        return $this->students()->where('active', true);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderByRaw('due_date IS NULL, due_date')->orderBy('id');
    }

    public function label(): string
    {
        return $this->grade ? "{$this->grade}° {$this->name}" : $this->name;
    }

    public function shiftLabel(): string
    {
        return self::SHIFTS[$this->shift] ?? $this->shift;
    }
}
