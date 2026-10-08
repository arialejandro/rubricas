<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RubricTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create();
        $this->group = $this->teacher->groups()->create(['name' => '3°B']);
    }

    private function projectWithCriteria(array $weights = ['Investigación' => 20, 'Presentación' => 80]): Project
    {
        $project = $this->group->projects()->create(['name' => 'Maqueta']);
        $i = 0;
        foreach ($weights as $name => $w) {
            $project->criteria()->create(['name' => $name, 'weight' => $w, 'position' => $i++]);
        }

        return $project->load('criteria');
    }

    public function test_bulk_add_students_parses_numbers_and_skips_duplicates(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('students.store', $this->group), ['names' => "1 Ana López\n2. Bruno Díaz\n\nana lópez\nCarla"])
            ->assertRedirect();

        $students = $this->group->students()->get();
        $this->assertSame(['Ana López', 'Bruno Díaz', 'Carla'], $students->pluck('name')->all());
        $this->assertSame(1, $students[0]->list_number);
        $this->assertNull($students[2]->list_number);
    }

    public function test_project_weights_must_sum_100(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('projects.store', $this->group), [
                'name' => 'Ensayo',
                'criteria' => [['name' => 'Ortografía', 'weight' => '30'], ['name' => 'Contenido', 'weight' => '60']],
            ])
            ->assertSessionHasErrors('criteria');

        $this->actingAs($this->teacher)
            ->post(route('projects.store', $this->group), [
                'name' => 'Ensayo',
                'criteria' => [['name' => 'Ortografía', 'weight' => '33,5'], ['name' => 'Contenido', 'weight' => '66.5']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Project::first()->criteria()->count());
    }

    public function test_grade_is_weighted_and_pending_is_tracked(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana']);
        $this->group->students()->create(['name' => 'Bruno']);
        [$research, $presentation] = $project->criteria;

        $url = route('grades.update', [$this->group, $project]);

        // 8 en un aspecto de 20% = 16%.
        $this->actingAs($this->teacher)
            ->putJson($url, ['student_id' => $ana->id, 'criterion_id' => $research->id, 'score' => '8'])
            ->assertOk()
            ->assertJsonPath('student.percent', 16)
            ->assertJsonPath('student.complete', false)
            ->assertJsonPath('project.missing', 3);

        // 9,5 (coma decimal) en 80% = 76% → total 92% = 9.2
        $this->actingAs($this->teacher)
            ->putJson($url, ['student_id' => $ana->id, 'criterion_id' => $presentation->id, 'score' => '9,5'])
            ->assertOk()
            ->assertJsonPath('student.percent', 92)
            ->assertJsonPath('student.final', 9.2)
            ->assertJsonPath('student.complete', true)
            ->assertJsonPath('project.missing', 2);

        // Vaciar = vuelve a pendiente (0 no es lo mismo que vacío).
        $this->actingAs($this->teacher)
            ->putJson($url, ['student_id' => $ana->id, 'criterion_id' => $research->id, 'score' => ''])
            ->assertOk()
            ->assertJsonPath('score', null)
            ->assertJsonPath('student.complete', false);

        $this->actingAs($this->teacher)
            ->putJson($url, ['student_id' => $ana->id, 'criterion_id' => $research->id, 'score' => '0'])
            ->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('student.complete', true);
    }

    public function test_score_out_of_range_is_rejected(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana']);

        $this->actingAs($this->teacher)
            ->putJson(route('grades.update', [$this->group, $project]), [
                'student_id' => $ana->id, 'criterion_id' => $project->criteria[0]->id, 'score' => '11',
            ])
            ->assertUnprocessable();
    }

    public function test_teachers_cannot_see_or_grade_each_others_groups(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('grupos.show', $this->group))->assertNotFound();
        $this->actingAs($intruder)->get(route('projects.show', [$this->group, $project]))->assertNotFound();
        $this->actingAs($intruder)->get(route('export.group', $this->group))->assertNotFound();
        $this->actingAs($intruder)
            ->putJson(route('grades.update', [$this->group, $project]), [
                'student_id' => $ana->id, 'criterion_id' => $project->criteria[0]->id, 'score' => '10',
            ])
            ->assertNotFound();

        // Un alumno de otro grupo no se puede calificar dentro de este proyecto.
        $otherGroup = $intruder->groups()->create(['name' => 'Ajeno']);
        $stranger = $otherGroup->students()->create(['name' => 'Ajeno']);
        $this->actingAs($this->teacher)
            ->putJson(route('grades.update', [$this->group, $project]), [
                'student_id' => $stranger->id, 'criterion_id' => $project->criteria[0]->id, 'score' => '10',
            ])
            ->assertUnprocessable();
    }

    public function test_inactive_students_do_not_count_as_pending(): void
    {
        $project = $this->projectWithCriteria(['Único' => 100]);
        $this->group->students()->create(['name' => 'Ana']);
        $this->group->students()->create(['name' => 'Bruno', 'active' => false]);

        $this->actingAs($this->teacher)->get(route('grupos.show', $this->group))
            ->assertOk()
            ->assertSee('1 calificaciones por capturar');
    }

    public function test_excel_student_list_import_with_header_and_uppercase_names(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Lista 3°B'],
            [],
            ['N.L.', 'Apellido paterno', 'Apellido materno', 'Nombre(s)'],
            [1, 'LÓPEZ', 'PÉREZ', 'ANA'],
            [2, 'Díaz', 'Ruiz', 'Bruno'],
        ]);
        // Título arriba de los encabezados: se ignora porque no tiene columna de nombre.
        $book->getActiveSheet()->removeRow(1, 2);
        $path = tempnam(sys_get_temp_dir(), 'lista').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        $this->group->students()->create(['name' => 'Diaz Ruiz Bruno', 'list_number' => 9]);

        $this->actingAs($this->teacher)
            ->post(route('students.import', $this->group), [
                'file' => new \Illuminate\Http\UploadedFile($path, 'lista.xlsx', null, null, true),
            ])
            ->assertRedirect(route('students.index', $this->group))
            ->assertSessionHas('status', 'Lista cargada: 1 nuevos · 1 con N.L. actualizado.');

        $this->assertSame(['López Pérez Ana' => 1, 'Diaz Ruiz Bruno' => 2], $this->group->students()->pluck('list_number', 'name')->all());
        @unlink($path);
    }

    public function test_csv_without_header_is_number_and_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lista');
        file_put_contents($path, "1,Ana López\n2,Bruno Díaz\n");

        $this->actingAs($this->teacher)
            ->post(route('students.import', $this->group), [
                'file' => new \Illuminate\Http\UploadedFile($path, 'lista.csv', 'text/csv', null, true),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Ana López', 'Bruno Díaz'], $this->group->students()->pluck('name')->all());
        @unlink($path);
    }

    public function test_a_teacher_has_at_most_one_group_per_shift(): void
    {
        $payload = ['shift' => 'matutino', 'grade' => 4, 'name' => 'c', 'school_name' => 'Escuela'];

        $this->actingAs($this->teacher)->post(route('grupos.store'), $payload)->assertSessionHasErrors('shift');

        $this->actingAs($this->teacher)->post(route('grupos.store'), [...$payload, 'shift' => 'vespertino'])->assertSessionHasNoErrors();
        $this->assertSame('C', $this->teacher->groups()->where('shift', 'vespertino')->value('name'));

        $this->actingAs($this->teacher)->get(route('grupos.create'))->assertRedirect(route('dashboard'));
        $this->actingAs($this->teacher)->post(route('grupos.store'), $payload)->assertStatus(422);
    }

    public function test_home_returns_to_last_visited_shift(): void
    {
        $evening = $this->teacher->groups()->create(['shift' => 'vespertino', 'name' => 'A', 'grade' => 5]);

        $this->actingAs($this->teacher)->get(route('grupos.show', $evening))->assertOk();
        $this->actingAs($this->teacher)->get(route('dashboard'))->assertRedirect(route('grupos.show', $evening));
    }

    public function test_main_screens_render(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana', 'list_number' => 1]);
        $project->criteria[0]->grades()->create(['student_id' => $ana->id, 'score' => 9]);

        $this->actingAs($this->teacher);
        $this->get(route('grupos.show', $this->group))->assertOk()->assertSee('Proyectos recientes');
        $this->get(route('projects.index', $this->group))->assertOk()->assertSee('Maqueta');
        $this->get(route('projects.show', [$this->group, $project]))->assertOk()->assertSee('data-score-cell', false);
        $this->get(route('capture', [$this->group, $project, $project->criteria[0]]))->assertOk()->assertSee('id="keypad"', false);
        $this->get(route('students.index', $this->group))->assertOk()->assertSee('Subir Excel');
        $this->get(route('students.show', [$this->group, $ana]))->assertOk()->assertSee('Faltan 1');
        $this->get(route('students.template', $this->group))->assertOk();
        $this->get(route('grupos.edit', $this->group))->assertOk()->assertSee('Zona escolar');
    }

    public function test_removing_graded_criterion_requires_confirmation(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana']);
        $project->criteria[0]->grades()->create(['student_id' => $ana->id, 'score' => 9]);

        $payload = ['name' => 'Maqueta', 'criteria' => [['id' => $project->criteria[1]->id, 'name' => 'Presentación', 'weight' => '100']]];

        $this->actingAs($this->teacher)->put(route('projects.update', [$this->group, $project]), $payload)
            ->assertSessionHasErrors('confirm_remove');
        $this->assertSame(2, $project->criteria()->count());

        $this->actingAs($this->teacher)->put(route('projects.update', [$this->group, $project]), [...$payload, 'confirm_remove' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $project->criteria()->count());
    }

    public function test_excel_export_has_summary_pending_and_formulas(): void
    {
        $project = $this->projectWithCriteria();
        $ana = $this->group->students()->create(['name' => 'Ana', 'list_number' => 1]);
        $this->group->students()->create(['name' => 'Bruno', 'list_number' => 2]);
        $project->criteria[0]->grades()->create(['student_id' => $ana->id, 'score' => 8]);
        $project->criteria[1]->grades()->create(['student_id' => $ana->id, 'score' => 10]);

        $response = $this->actingAs($this->teacher)->get(route('export.group', $this->group));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        unlink($path);

        $this->assertSame(['Resumen', 'Pendientes', 'Maqueta'], $book->getSheetNames());

        $sheet = $book->getSheetByName('Maqueta');
        $this->assertSame('Ana', $sheet->getCell('B6')->getValue());
        // 8×20% + 10×80% = 96% → 9.6
        $this->assertEqualsWithDelta(96, $sheet->getCell('E6')->getCalculatedValue(), 0.001);
        $this->assertEqualsWithDelta(9.6, $sheet->getCell('F6')->getCalculatedValue(), 0.001);
        $this->assertSame('Completo', $sheet->getCell('G6')->getCalculatedValue());
        $this->assertSame('Faltan 2', $sheet->getCell('G7')->getCalculatedValue());

        $this->assertEqualsWithDelta(9.6, $book->getSheetByName('Resumen')->getCell('C5')->getCalculatedValue(), 0.001);
        $this->assertSame('Bruno', $book->getSheetByName('Pendientes')->getCell('D2')->getValue());
    }
}
