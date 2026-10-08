<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'weight', 'position'])]
class Criterion extends Model
{
    protected $table = 'criteria';
    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
