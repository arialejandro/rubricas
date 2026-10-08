<?php

namespace App\Models;

use App\Support\Campos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proyecto de un trimestre. `campo` es el campo donde se planteó; cada producto puede
 * evaluarse en otro campo (transversalidad) y suma a ESE campo.
 */
#[Fillable(['term', 'campo', 'name', 'description', 'pdas', 'due_date'])]
class Project extends Model
{
    public const MAX_PDAS = 4;

    protected function casts(): array
    {
        return ['due_date' => 'date', 'pdas' => 'array', 'term' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('position')->orderBy('id');
    }

    public function campoName(): string
    {
        return Campos::name($this->campo);
    }

    /** Campos en los que suma este proyecto (el principal + los de sus productos). */
    public function campos(): array
    {
        return collect([$this->campo])->merge($this->products->pluck('campo'))->unique()->values()->all();
    }
}
