<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['criterion_id', 'student_id', 'score'])]
class CriterionScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
