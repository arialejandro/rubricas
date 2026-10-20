<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilidades prioritarias (velocidad lectora, comprensión, cálculo mental…): solo en Lenguajes y
 * Saberes. Son del grupo (todo el ciclo) y se califican por trimestre con nivel 6–10. Suman al campo
 * a través de un aspecto de tipo "skills" con su porcentaje (ver App\Support\TermBook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('campo', 20);
            $table->string('name', 120);
            $table->text('level_logrado')->nullable();
            $table->text('level_satisfactorio')->nullable();
            $table->text('level_proceso')->nullable();
            $table->text('level_apoyo')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term');
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['skill_id', 'student_id', 'term']);
        });

        // Los grupos que ya existen reciben las habilidades sugeridas (editables).
        $now = now();
        foreach (DB::table('groups')->pluck('id') as $groupId) {
            foreach (\App\Support\Campos::DEFAULT_SKILLS as $campo => $names) {
                foreach ($names as $i => $name) {
                    DB::table('skills')->insert(['group_id' => $groupId, 'campo' => $campo, 'name' => $name, 'position' => $i, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_scores');
        Schema::dropIfExists('skills');
    }
};
