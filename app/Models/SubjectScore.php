<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['subject_id', 'student_id', 'term', 'score'])]
class SubjectScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float', 'term' => 'integer'];
    }
}
