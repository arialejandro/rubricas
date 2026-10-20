<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilidades prioritarias, segunda vuelta:
 *  - `grading`: cómo se califica → level (elijo el nivel) | measure (mido y se calcula) | criteria (por criterios).
 *  - Aplicaciones: una habilidad puede evaluarse varias veces en el trimestre (ej. Multiplicación
 *    el 5, el 19 y el 30). Cada calificación pertenece a una aplicación; para el campo cuenta el
 *    PROMEDIO de las aplicaciones (decisión de la dueña, 2026-10-21) y el historial da la trazabilidad.
 *  - Criterios propios de la habilidad (con descriptores por nivel), calificados por aplicación.
 * Las calificaciones ya capturadas pasan a una "Aplicación 1" de su trimestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('grading', 10)->default('level')->after('name');
        });
        DB::table('skills')->whereNotNull('measure_unit')->update(['grading' => 'measure']);

        Schema::create('skill_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term');
            $table->date('applied_on')->nullable();
            $table->string('label', 80)->nullable();
            $table->timestamps();
            $table->index(['skill_id', 'term']);
        });

        Schema::create('skill_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->text('level_logrado')->nullable();
            $table->text('level_satisfactorio')->nullable();
            $table->text('level_proceso')->nullable();
            $table->text('level_apoyo')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_criterion_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_criterion_id')->constrained('skill_criteria')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['skill_application_id', 'skill_criterion_id', 'student_id'], 'skill_crit_score_unique');
        });

        Schema::table('skill_scores', function (Blueprint $table) {
            $table->foreignId('skill_application_id')->nullable()->after('skill_id')->constrained()->cascadeOnDelete();
        });

        // Calificaciones existentes → "Aplicación 1" de su trimestre.
        $now = now();
        foreach (DB::table('skill_scores')->select('skill_id', 'term')->distinct()->get() as $pair) {
            $appId = DB::table('skill_applications')->insertGetId([
                'skill_id' => $pair->skill_id, 'term' => $pair->term, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('skill_scores')->where('skill_id', $pair->skill_id)->where('term', $pair->term)
                ->update(['skill_application_id' => $appId]);
        }

        // MySQL no deja quitar el único si la llave foránea de skill_id se apoya en él: primero un índice propio.
        Schema::table('skill_scores', fn (Blueprint $table) => $table->index('skill_id', 'skill_scores_skill_id_idx'));
        Schema::table('skill_scores', function (Blueprint $table) {
            $table->dropUnique(['skill_id', 'student_id', 'term']);
            $table->unique(['skill_application_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::table('skill_scores', function (Blueprint $table) {
            $table->dropUnique(['skill_application_id', 'student_id']);
            $table->dropConstrainedForeignId('skill_application_id');
            $table->unique(['skill_id', 'student_id', 'term']);
        });
        Schema::dropIfExists('skill_criterion_scores');
        Schema::dropIfExists('skill_criteria');
        Schema::dropIfExists('skill_applications');
        Schema::table('skills', fn (Blueprint $t) => $t->dropColumn('grading'));
    }
};
