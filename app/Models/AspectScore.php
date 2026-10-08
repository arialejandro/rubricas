<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['term_aspect_id', 'student_id', 'score'])]
class AspectScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float'];
    }
}
