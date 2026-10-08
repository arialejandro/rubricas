<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grupo (salón) — pertenece a una maestra. Todo lo demás cuelga de aquí,
        // así el aislamiento entre maestras se resuelve en un solo punto.
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('school_year')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('list_number')->nullable();
            // Un alumno dado de baja conserva sus calificaciones pero deja de contar como pendiente.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        // Aspecto de evaluación de la rúbrica; weight = porcentaje del proyecto (deben sumar 100).
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Calificación 0–10 de un alumno en un aspecto. Sin fila = pendiente (distinto de 0).
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 4, 2);
            $table->timestamps();

            $table->unique(['criterion_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('students');
        Schema::dropIfExists('groups');
    }
};
