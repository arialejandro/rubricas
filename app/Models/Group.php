<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'school_year'])]
class Group extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class)->orderByRaw('list_number IS NULL, list_number')->orderBy('name');
    }

    public function activeStudents(): HasMany
    {
        return $this->students()->where('active', true);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderByRaw('due_date IS NULL, due_date')->orderBy('id');
    }
}
