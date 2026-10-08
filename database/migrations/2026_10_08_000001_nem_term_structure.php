<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura de evaluación NEM (reemplaza al modelo "proyecto con rúbrica ponderada"):
 *
 *   Trimestre (1–3) → Campo formativo (catálogo fijo en App\Support\Campos)
 *     → aspectos con % (texto libre; tipo "projects" = sale de los proyectos, "direct" = se captura)
 *     → + materias adicionales (Artes, Inglés, Ed. Física) que promedian en partes iguales con el campo.
 *   Proyecto (trimestre, campo principal, 1–4 PDA) → productos (cada uno ligado a un campo + instrumento)
 *     → criterios (con descriptores de los 4 niveles) → calificación por nivel 6–10.
 *
 * Los proyectos/calificaciones de la prueba en campo se descartan (decisión de la dueña, 2026-10-08):
 * no tenían trimestre ni campo. Cuentas, grupos y alumnos se conservan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('projects');

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_term')->default(1)->after('school_zone');
        });

        // Materias adicionales del grupo (todo el ciclo); su calificación se captura por trimestre.
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('campo', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('subject_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term');
            $table->decimal('score', 4, 2);
            $table->timestamps();
            $table->unique(['subject_id', 'student_id', 'term']);
        });

        // Aspectos de evaluación de un campo en un trimestre (la "estrategia" cambia cada trimestre).
        Schema::create('term_aspects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term');
            $table->string('campo', 20);
            $table->string('name', 120);
            $table->string('type', 10)->default('direct'); // projects | direct
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['group_id', 'term', 'campo']);
        });

        Schema::create('aspect_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_aspect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 4, 2);
            $table->timestamps();
            $table->unique(['term_aspect_id', 'student_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term');
            $table->string('campo', 20); // campo principal (dónde se planteó)
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('pdas')->nullable(); // 1–4 descripciones de PDA
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'term']);
        });

        // Producto del proyecto: se evalúa en SU campo (transversalidad), con un instrumento.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('campo', 20);
            $table->string('name');
            $table->string('instrument', 80)->nullable(); // Rúbrica, Lista de cotejo, Escala…
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Criterio a observar + descriptor de cada nivel (sale de la rúbrica del instrumento).
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->text('level_logrado')->nullable();
            $table->text('level_satisfactorio')->nullable();
            $table->text('level_proceso')->nullable();
            $table->text('level_apoyo')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Nivel alcanzado: 10 Logrado · 9 Satisfactorio · 8/7 En proceso · 6 Requiere apoyo.
        Schema::create('criterion_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['criterion_id', 'student_id']);
        });

        // Grupos que ya existen reciben sus materias adicionales por omisión.
        $now = now();
        foreach (DB::table('groups')->pluck('id') as $groupId) {
            DB::table('subjects')->insert([
                ['group_id' => $groupId, 'campo' => 'lenguajes', 'name' => 'Artes', 'position' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['group_id' => $groupId, 'campo' => 'lenguajes', 'name' => 'Inglés', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['group_id' => $groupId, 'campo' => 'humano', 'name' => 'Educación Física', 'position' => 0, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('criterion_scores');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('products');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('aspect_scores');
        Schema::dropIfExists('term_aspects');
        Schema::dropIfExists('subject_scores');
        Schema::dropIfExists('subjects');
        Schema::table('groups', fn (Blueprint $t) => $t->dropColumn('current_term'));
        // El modelo anterior (projects/criteria/grades) no se restaura: sus datos ya se descartaron.
    }
};
