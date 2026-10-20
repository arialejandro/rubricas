<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilidades medibles: en vez de elegir el nivel, la maestra captura una medición
 * (p. ej. palabras por minuto) y la calificación se calcula con sus rangos:
 *   min_7, min_8, min_9, min_10 = desde cuánto vale 7, 8, 9 y 10 (debajo de min_7 = 6).
 *   lower_is_better invierte la regla (p. ej. errores: "hasta" en vez de "desde").
 * level_labels permite nombrar los niveles a su manera (Avanzado, Estándar…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('measure_unit', 40)->nullable()->after('name');
            $table->boolean('lower_is_better')->default(false)->after('measure_unit');
            $table->decimal('min_7', 8, 2)->nullable()->after('lower_is_better');
            $table->decimal('min_8', 8, 2)->nullable()->after('min_7');
            $table->decimal('min_9', 8, 2)->nullable()->after('min_8');
            $table->decimal('min_10', 8, 2)->nullable()->after('min_9');
            $table->json('level_labels')->nullable()->after('min_10');
        });

        Schema::table('skill_scores', function (Blueprint $table) {
            $table->decimal('measure', 8, 2)->nullable()->after('score');
        });

        // Velocidad lectora ya existente → medible con los rangos de la maestra (ajustables).
        DB::table('skills')->where('name', 'Velocidad lectora')->whereNull('measure_unit')->update(
            \App\Support\Campos::READING_SPEED_DEFAULTS + ['level_labels' => json_encode(\App\Support\Campos::STANDARD_LABELS, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function down(): void
    {
        Schema::table('skill_scores', fn (Blueprint $t) => $t->dropColumn('measure'));
        Schema::table('skills', fn (Blueprint $t) => $t->dropColumn(['measure_unit', 'lower_is_better', 'min_7', 'min_8', 'min_9', 'min_10', 'level_labels']));
    }
};
