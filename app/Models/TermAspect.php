<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aspecto de evaluación de un campo formativo en un trimestre.
 * type "projects": su valor sale de los productos de proyectos de ese campo (no se captura).
 * type "direct": se captura alumno por alumno (Examen, Tareas…).
 * type "skills": promedio de las habilidades prioritarias del campo (solo Lenguajes y Saberes).
 */
#[Fillable(['term', 'campo', 'name', 'type', 'weight', 'position'])]
class TermAspect extends Model
{
    public const TYPES = ['projects' => 'De los proyectos', 'direct' => 'Captura directa', 'skills' => 'Habilidades prioritarias'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'term' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AspectScore::class);
    }

    public function isFromProjects(): bool
    {
        return $this->type === 'projects';
    }

    public function isFromSkills(): bool
    {
        return $this->type === 'skills';
    }

    /** Se captura alumno por alumno (no se calcula de otra parte). */
    public function isDirect(): bool
    {
        return $this->type === 'direct';
    }
}
