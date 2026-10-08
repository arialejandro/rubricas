<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'description', 'due_date'])]
class Project extends Model
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('position')->orderBy('id');
    }

    public function grades(): HasManyThrough
    {
        return $this->hasManyThrough(Grade::class, Criterion::class);
    }

    public function totalWeight(): float
    {
        return round((float) $this->criteria->sum('weight'), 2);
    }

    public function weightsAreValid(): bool
    {
        return abs($this->totalWeight() - 100) < 0.01;
    }
}
