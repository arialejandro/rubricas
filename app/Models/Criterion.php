<?php

namespace App\Models;

use App\Support\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Criterio a observar de un producto, con el descriptor de cada nivel de la rúbrica. */
#[Fillable(['description', 'level_logrado', 'level_satisfactorio', 'level_proceso', 'level_apoyo', 'position'])]
class Criterion extends Model
{
    protected $table = 'criteria';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CriterionScore::class);
    }

    /** @return array<string, string> nivel => descriptor (solo los que tienen texto) */
    public function descriptors(): array
    {
        $out = [];
        foreach (array_keys(Level::LEVELS) as $level) {
            if (filled($this->{Level::column($level)})) {
                $out[$level] = $this->{Level::column($level)};
            }
        }

        return $out;
    }
}
