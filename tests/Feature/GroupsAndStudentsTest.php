<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Cuentas, grupos (turnos), aislamiento entre maestras y lista de alumnos. */
class GroupsAndStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create();
        $this->group = $this->teacher->groups()->create(['name' => 'B', 'grade' => 3]);
    }

    public function test_new_group_gets_default_extra_subjects(): void
    {
        $this->assertSame(
            ['lenguajes' => ['Artes', 'Inglés'], 'humano' => ['Educación Física']],
            $this->group->subjects()->get()->groupBy('campo')->map(fn ($s) => $s->pluck('name')->all())->all(),
        );
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

    public function test_excel_student_list_import_with_header_and_uppercase_names(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['N.L.', 'Apellido paterno', 'Apellido materno', 'Nombre(s)'],
            [1, 'LÓPEZ', 'PÉREZ', 'ANA'],
            [2, 'Díaz', 'Ruiz', 'Bruno'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'lista').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->group->students()->create(['name' => 'Diaz Ruiz Bruno', 'list_number' => 9]);

        $this->actingAs($this->teacher)
            ->post(route('students.import', $this->group), ['file' => new UploadedFile($path, 'lista.xlsx', null, null, true)])
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
            ->post(route('students.import', $this->group), ['file' => new UploadedFile($path, 'lista.csv', 'text/csv', null, true)])
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

    public function test_teachers_cannot_reach_each_others_groups(): void
    {
        $intruder = User::factory()->create();
        $ana = $this->group->students()->create(['name' => 'Ana']);
        $aspect = $this->group->termAspects()->create(['term' => 1, 'campo' => 'lenguajes', 'name' => 'Examen', 'type' => 'direct', 'weight' => 100]);

        $this->actingAs($intruder)->get(route('grupos.show', $this->group))->assertNotFound();
        $this->actingAs($intruder)->get(route('campos.show', [$this->group, 'lenguajes']))->assertNotFound();
        $this->actingAs($intruder)->get(route('export.group', $this->group))->assertNotFound();
        $this->actingAs($intruder)
            ->putJson(route('score.update', $this->group), ['kind' => 'aspect', 'id' => $aspect->id, 'student_id' => $ana->id, 'score' => '10'])
            ->assertNotFound();

        // Desde su propio grupo tampoco puede tocar un aspecto ajeno.
        $own = $intruder->groups()->create(['name' => 'X', 'grade' => 1]);
        $stranger = $own->students()->create(['name' => 'Otro']);
        $this->actingAs($intruder)
            ->putJson(route('score.update', $own), ['kind' => 'aspect', 'id' => $aspect->id, 'student_id' => $stranger->id, 'score' => '10'])
            ->assertNotFound();
    }

    public function test_teacher_account_command_creates_and_resets(): void
    {
        $this->artisan('maestra:cuenta', ['email' => 'Ana@Escuela.mx', '--nombre' => 'Ana', '--password' => 'secreta123'])->assertSuccessful();
        $this->assertTrue(auth()->attempt(['email' => 'ana@escuela.mx', 'password' => 'secreta123']));

        $this->artisan('maestra:cuenta', ['email' => 'ana@escuela.mx', '--password' => 'otra12345'])->assertSuccessful();
        $this->assertTrue(auth()->attempt(['email' => 'ana@escuela.mx', 'password' => 'otra12345']));
        $this->assertSame(1, User::where('email', 'ana@escuela.mx')->count());

        $this->artisan('maestra:cuenta', ['email' => 'nueva@escuela.mx'])->assertFailed();
    }

    public function test_registration_can_be_closed(): void
    {
        config(['rubrica.registration' => false]);
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), ['name' => 'X', 'email' => 'x@x.mx', 'password' => 'secreta123', 'password_confirmation' => 'secreta123'])
            ->assertNotFound();
    }
}
