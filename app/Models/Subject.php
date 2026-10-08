<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Materia adicional de un campo (Artes, Inglés, Educación Física). Llega solo con su calificación
 * por trimestre y promedia en partes iguales con la calificación del campo.
 */
#[Fillable(['campo', 'name', 'position'])]
class Subject extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SubjectScore::class);
    }
}
