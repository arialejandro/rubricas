<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['skill_id', 'student_id', 'term', 'score'])]
class SkillScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'integer', 'term' => 'integer'];
    }
}
