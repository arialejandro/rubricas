<?php

namespace App\Models;

use App\Support\Campos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo = salón de una maestra en un turno. `name` es la letra del grupo ("B");
 * label() lo arma con el grado: "3° B". `current_term` es el trimestre en el que se trabaja.
 */
#[Fillable(['shift', 'grade', 'name', 'school_year', 'school_name', 'school_cct', 'school_zone', 'current_term'])]
class Group extends Model
{
    public const SHIFTS = ['matutino' => 'Matutino', 'vespertino' => 'Vespertino'];

    public const MAX_PER_TEACHER = 2;

    protected function casts(): array
    {
        return ['current_term' => 'integer'];
    }

    protected static function booted(): void
    {
        // Cada grupo nace con sus materias adicionales (Artes, Inglés, Educación Física).
        static::created(function (Group $group) {
            foreach (Campos::DEFAULT_SUBJECTS as $campo => $names) {
                foreach ($names as $i => $name) {
                    $group->subjects()->create(['campo' => $campo, 'name' => $name, 'position' => $i]);
                }
            }
        });
    }

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

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('position')->orderBy('id');
    }

    public function termAspects(): HasMany
    {
        return $this->hasMany(TermAspect::class)->orderBy('position')->orderBy('id');
    }

    public function label(): string
    {
        return $this->grade ? "{$this->grade}° {$this->name}" : $this->name;
    }

    public function shiftLabel(): string
    {
        return self::SHIFTS[$this->shift] ?? $this->shift;
    }

    public function term(): int
    {
        return in_array($this->current_term, Campos::TERMS, true) ? $this->current_term : 1;
    }
}
