<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Producto de un proyecto: se evalúa con un instrumento y suma a SU campo formativo. */
#[Fillable(['campo', 'name', 'instrument', 'position'])]
class Product extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('position')->orderBy('id');
    }
}
