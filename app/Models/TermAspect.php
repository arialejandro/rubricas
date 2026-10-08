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
 */
#[Fillable(['term', 'campo', 'name', 'type', 'weight', 'position'])]
class TermAspect extends Model
{
    public const TYPES = ['projects' => 'De los proyectos', 'direct' => 'Captura directa'];

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
}
