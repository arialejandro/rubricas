<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['skill_application_id', 'skill_criterion_id', 'student_id', 'score'])]
class SkillCriterionScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
