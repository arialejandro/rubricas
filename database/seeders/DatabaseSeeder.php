<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración (solo local): maestra demo@rubrica.test / demo1234,
 * un grupo de 28 alumnos y dos proyectos, uno a medio calificar.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Maestra Demo',
            'email' => 'demo@rubrica.test',
            'password' => 'demo1234',
        ]);

        $group = $teacher->groups()->create(['name' => '3°B', 'school_year' => '2026-2027']);

        $names = ['Ana López', 'Bruno Díaz', 'Carla Méndez', 'Daniel Ortiz', 'Elena Ruiz', 'Fernando Gil',
            'Gabriela Soto', 'Héctor Navarro', 'Isabel Cruz', 'Jorge Ramos', 'Karla Vega', 'Luis Torres',
            'María Fernández', 'Nicolás Herrera', 'Olivia Castro', 'Pablo Morales', 'Regina Flores',
            'Santiago Reyes', 'Sofía Jiménez', 'Tomás Aguilar', 'Valeria Romero', 'Ximena Salazar',
            'Yahir Peña', 'Zoe Medina', 'Andrés Vargas', 'Camila Ríos', 'Diego Paredes', 'Renata Luna'];
        $students = collect($names)->sort()->values()
            ->map(fn ($n, $i) => $group->students()->create(['name' => $n, 'list_number' => $i + 1]));

        $maqueta = $group->projects()->create(['name' => 'Maqueta del sistema solar', 'due_date' => now()->subWeek()]);
        $maqueta->criteria()->createMany([
            ['name' => 'Investigación', 'weight' => 20, 'position' => 0],
            ['name' => 'Creatividad', 'weight' => 30, 'position' => 1],
            ['name' => 'Exposición', 'weight' => 50, 'position' => 2],
        ]);

        $ensayo = $group->projects()->create(['name' => 'Ensayo: el agua', 'due_date' => now()->addWeek()]);
        $ensayo->criteria()->createMany([
            ['name' => 'Ortografía', 'weight' => 40, 'position' => 0],
            ['name' => 'Contenido', 'weight' => 60, 'position' => 1],
        ]);

        // Maqueta: dos aspectos completos, el tercero a medias → hay pendientes que perseguir.
        foreach ($maqueta->criteria as $ci => $criterion) {
            foreach ($students as $si => $student) {
                if ($ci === 2 && $si % 3 === 0) {
                    continue;
                }
                $criterion->grades()->create(['student_id' => $student->id, 'score' => fake()->randomElement([6, 7, 7.5, 8, 8.5, 9, 9.5, 10])]);
            }
        }
    }
}
