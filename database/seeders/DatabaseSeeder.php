<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración (solo local): maestra demo@rubrica.test / demo1234
 * con grupo matutino (28 alumnos, un proyecto a medias) y vespertino (22 alumnos).
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

        $school = ['school_name' => 'Esc. Prim. Benito Juárez', 'school_cct' => '09DPR1234X', 'school_zone' => '015', 'school_year' => '2026-2027'];

        $morning = $teacher->groups()->create([...$school, 'shift' => 'matutino', 'grade' => 3, 'name' => 'B']);
        $evening = $teacher->groups()->create([...$school, 'school_name' => 'Esc. Prim. Niños Héroes', 'school_cct' => '09DPR5678Y', 'shift' => 'vespertino', 'grade' => 5, 'name' => 'A']);

        $this->fill($morning, 28, true);
        $this->fill($evening, 22, false);
    }

    private function fill(Group $group, int $count, bool $withGrades): void
    {
        $surnames = ['López', 'Martínez', 'García', 'Hernández', 'Pérez', 'Sánchez', 'Ramírez', 'Cruz', 'Flores', 'Gómez',
            'Morales', 'Vázquez', 'Reyes', 'Jiménez', 'Torres', 'Díaz', 'Gutiérrez', 'Ruiz', 'Mendoza', 'Aguilar'];
        $names = ['Ana Sofía', 'Bruno', 'Carla', 'Diego', 'Elena', 'Fernando', 'Gabriela', 'Héctor', 'Isabel', 'Jorge',
            'Karla', 'Luis', 'María José', 'Nicolás', 'Olivia', 'Pablo', 'Regina', 'Santiago', 'Valeria', 'Ximena', 'Yahir', 'Zoe'];

        $list = collect(range(1, $count))
            ->map(fn () => fake()->randomElement($surnames).' '.fake()->randomElement($surnames).' '.fake()->randomElement($names))
            ->unique()->sort()->values();
        $list->each(fn ($n, $i) => $group->students()->create(['name' => $n, 'list_number' => $i + 1]));

        NemDemoSeeder::seedGroup($group, $withGrades);
    }
}
