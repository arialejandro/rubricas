<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use App\Support\Level;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración de la evaluación NEM (solo local, NUNCA en el servidor):
 * aspectos de los 4 campos en el Trimestre 1, dos proyectos transversales con productos,
 * criterios y descriptores, y calificaciones a medias para que haya pendientes.
 *
 *   php artisan db:seed --class=NemDemoSeeder   → a los grupos de demo@rubrica.test que no tengan aspectos
 */
class NemDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@rubrica.test')->first();
        $user?->groups()->get()
            ->reject(fn (Group $g) => $g->termAspects()->exists())
            ->each(fn (Group $g) => self::seedGroup($g, $g->shift === 'matutino'));
    }

    public static function seedGroup(Group $group, bool $withScores = true): void
    {
        $strategy = [
            'lenguajes' => [['Proyectos', 'projects', 40], ['Examen', 'direct', 30], ['Lectura en voz alta', 'direct', 15], ['Tareas', 'direct', 15]],
            'saberes' => [['Proyectos', 'projects', 50], ['Examen', 'direct', 30], ['Cuaderno', 'direct', 20]],
            'etica' => [['Proyectos', 'projects', 40], ['Examen', 'direct', 40], ['Participación', 'direct', 20]],
            'humano' => [['Proyectos', 'projects', 30], ['Convivencia', 'direct', 40], ['Participación', 'direct', 30]],
        ];
        foreach ($strategy as $campo => $aspects) {
            foreach ($aspects as $i => [$name, $type, $weight]) {
                $group->termAspects()->create(['term' => 1, 'campo' => $campo, 'name' => $name, 'type' => $type, 'weight' => $weight, 'position' => $i]);
            }
        }

        // Proyecto de Lenguajes con un producto que suma a Ética (transversal).
        $news = $group->projects()->create([
            'term' => 1, 'campo' => 'lenguajes', 'name' => 'El periódico escolar', 'due_date' => now()->subWeek(),
            'pdas' => [
                'Comprende y produce textos informativos (noticia) para difundir hechos de su comunidad.',
                'Distingue entre hechos y opiniones en textos periodísticos.',
            ],
        ]);
        $note = $news->products()->create(['campo' => 'lenguajes', 'name' => 'Noticia escrita', 'instrument' => 'Rúbrica', 'position' => 0]);
        $note->criteria()->createMany([
            ['description' => 'Informa un hecho real', 'position' => 0,
                'level_logrado' => 'Informa un hecho real con las 5 preguntas y al menos un dato propio.',
                'level_satisfactorio' => 'Informa el hecho con casi todas las preguntas.',
                'level_proceso' => 'Mezcla hechos y opiniones o mezcla datos.',
                'level_apoyo' => 'El hecho no se comprende.'],
            ['description' => 'Ortografía y puntuación', 'position' => 1,
                'level_logrado' => 'Sin errores que dificulten la lectura.',
                'level_satisfactorio' => 'Pocos errores.',
                'level_proceso' => 'Errores frecuentes.',
                'level_apoyo' => 'Los errores impiden entender el texto.'],
        ]);
        $debate = $news->products()->create(['campo' => 'etica', 'name' => 'Mesa de opinión', 'instrument' => 'Lista de cotejo', 'position' => 1]);
        $debate->criteria()->createMany([
            ['description' => 'Respeta turnos y opiniones distintas', 'position' => 0],
            ['description' => 'Argumenta su postura con un ejemplo', 'position' => 1],
        ]);

        // Proyecto de Saberes.
        $plants = $group->projects()->create([
            'term' => 1, 'campo' => 'saberes', 'name' => 'Germinación de semillas', 'due_date' => now()->addWeek(),
            'pdas' => ['Describe el ciclo de vida de las plantas a partir de la observación.'],
        ]);
        $log = $plants->products()->create(['campo' => 'saberes', 'name' => 'Bitácora de observación', 'instrument' => 'Escala estimativa', 'position' => 0]);
        $log->criteria()->createMany([
            ['description' => 'Registra cambios con fecha y dibujo', 'position' => 0],
            ['description' => 'Formula una explicación de lo observado', 'position' => 1],
        ]);

        if (! $withScores) {
            return;
        }

        $students = $group->activeStudents()->get();
        $levels = Level::CRITERION_SCORES;
        foreach ([$note, $debate] as $product) {
            foreach ($product->criteria as $c) {
                foreach ($students as $i => $s) {
                    if ($product->is($debate) && $i % 4 === 0) {
                        continue; // pendientes a propósito
                    }
                    $c->scores()->create(['student_id' => $s->id, 'score' => $levels[array_rand($levels)]]);
                }
            }
        }
        $exam = $group->termAspects()->where('campo', 'lenguajes')->where('name', 'Examen')->first();
        foreach ($students as $i => $s) {
            if ($i % 5 !== 0) {
                $exam->scores()->create(['student_id' => $s->id, 'score' => fake()->randomElement([6, 7, 7.5, 8, 8.5, 9, 9.5, 10])]);
            }
        }
        $english = $group->subjects()->where('name', 'Inglés')->first();
        foreach ($students->take(10) as $s) {
            $english?->scores()->create(['student_id' => $s->id, 'term' => 1, 'score' => fake()->randomElement([7, 8, 9, 10])]);
        }
    }
}
