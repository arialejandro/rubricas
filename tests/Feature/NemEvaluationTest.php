<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Group;
use App\Models\Product;
use App\Models\Project;
use App\Models\Student;
use App\Models\TermAspect;
use App\Models\User;
use App\Support\TermBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Evaluación NEM: trimestre → campo → aspectos con % + materias; proyectos transversales
 * cuyos productos suman al campo del producto; criterios por nivel 6–10.
 */
class NemEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Group $group;

    private Student $ana;

    private Student $bruno;

    private TermAspect $exam;

    private Project $project;

    private Product $letter;   // producto de un proyecto de Saberes que se evalúa en Lenguajes

    private Criterion $c1;

    private Criterion $c2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create();
        $this->group = $this->teacher->groups()->create(['name' => 'C', 'grade' => 4, 'school_name' => 'Esc. Prim. Juárez']);
        $this->ana = $this->group->students()->create(['name' => 'Ana', 'list_number' => 1]);
        $this->bruno = $this->group->students()->create(['name' => 'Bruno', 'list_number' => 2]);

        $this->group->termAspects()->create(['term' => 1, 'campo' => 'lenguajes', 'name' => 'Proyectos', 'type' => 'projects', 'weight' => 50, 'position' => 0]);
        $this->exam = $this->group->termAspects()->create(['term' => 1, 'campo' => 'lenguajes', 'name' => 'Examen', 'type' => 'direct', 'weight' => 50, 'position' => 1]);

        $this->project = $this->group->projects()->create(['term' => 1, 'campo' => 'saberes', 'name' => 'Germinación', 'pdas' => ['Observa el ciclo de vida']]);
        $this->letter = $this->project->products()->create(['campo' => 'lenguajes', 'name' => 'Informe escrito', 'instrument' => 'Rúbrica']);
        $this->c1 = $this->letter->criteria()->create(['description' => 'Informa un hecho real', 'level_logrado' => 'Con las 5 preguntas', 'position' => 0]);
        $this->c2 = $this->letter->criteria()->create(['description' => 'Ortografía', 'position' => 1]);
        $this->project->products()->create(['campo' => 'saberes', 'name' => 'Bitácora'])->criteria()->create(['description' => 'Registra cambios']);
    }

    private function score(string $kind, int $id, Student $s, string $score, ?int $term = null)
    {
        return $this->actingAs($this->teacher)->putJson(route('score.update', $this->group), array_filter([
            'kind' => $kind, 'id' => $id, 'student_id' => $s->id, 'score' => $score, 'term' => $term,
        ], fn ($v) => $v !== null));
    }

    private function gradeAnaLenguajes(): void
    {
        $artes = $this->group->subjects()->where('name', 'Artes')->first();
        $ingles = $this->group->subjects()->where('name', 'Inglés')->first();

        $this->score('criterion', $this->c1->id, $this->ana, '10')->assertOk();
        $this->score('criterion', $this->c2->id, $this->ana, '8')->assertOk();
        $this->score('aspect', $this->exam->id, $this->ana, '7')->assertOk();
        $this->score('subject', $artes->id, $this->ana, '10', 1)->assertOk();
        $this->score('subject', $ingles->id, $this->ana, '9', 1)->assertOk();
    }

    public function test_campo_grade_is_transversal_weighted_and_averaged_with_subjects(): void
    {
        $this->gradeAnaLenguajes();
        $book = TermBook::for($this->group->fresh(), 1);

        // Producto (10+8)/2 = 9 → aspecto Proyectos de LENGUAJES aunque el proyecto sea de Saberes.
        $this->assertSame(9.0, $book->productScore($book->productsFor('lenguajes')->first(), $this->ana));
        $this->assertSame(9.0, $book->projectsValue('lenguajes', $this->ana));
        $this->assertNull($book->projectsValue('saberes', $this->ana)); // la bitácora no está calificada

        // Base = 9×50% + 7×50% = 8; Final = promedio(8, Artes 10, Inglés 9) = 9.
        $this->assertSame(8.0, $book->campoBase('lenguajes', $this->ana));
        $this->assertSame(9.0, $book->campoFinal('lenguajes', $this->ana));
        $this->assertTrue($book->campoComplete('lenguajes', $this->ana));

        $this->assertSame(5, $book->campoMissing('lenguajes', $this->bruno));
        $this->assertFalse($book->campoComplete('lenguajes', $this->bruno));
    }

    public function test_partial_base_is_provisional_and_reweighted(): void
    {
        $this->score('aspect', $this->exam->id, $this->ana, '7')->assertOk();
        $book = TermBook::for($this->group->fresh(), 1);

        $this->assertSame(7.0, $book->campoBase('lenguajes', $this->ana)); // solo el examen tiene valor
        $this->assertFalse($book->campoComplete('lenguajes', $this->ana));
    }

    public function test_criteria_only_accept_level_scores(): void
    {
        $this->score('criterion', $this->c1->id, $this->ana, '7.5')->assertUnprocessable();
        $this->score('criterion', $this->c1->id, $this->ana, '5')->assertUnprocessable();
        $this->score('criterion', $this->c1->id, $this->ana, '7')
            ->assertOk()
            ->assertJsonPath('level', 'proceso')
            ->assertJsonPath('paint.text.missing:criterion:'.$this->c1->id, '1 sin calificar');

        // Vaciar = vuelve a pendiente.
        $this->score('criterion', $this->c1->id, $this->ana, '')->assertOk()->assertJsonPath('score', null);
    }

    public function test_save_returns_values_to_repaint(): void
    {
        $this->gradeAnaLenguajes();
        // Base = 9×50% + 9×50% = 9; Final = promedio(9, Artes 10, Inglés 9) = 9.33 → Satisfactorio.
        $this->score('aspect', $this->exam->id, $this->ana, '9')
            ->assertOk()
            ->assertJsonPath('paint.text.final:lenguajes:'.$this->ana->id, '9.33')
            ->assertJsonPath('paint.level.final:lenguajes:'.$this->ana->id, 'satisfactorio')
            ->assertJsonPath('paint.text.status:lenguajes:'.$this->ana->id, 'Completo');
    }

    public function test_campo_aspects_must_sum_100_and_allow_one_projects_aspect(): void
    {
        $url = route('campos.update', [$this->group, 'etica']);
        $this->actingAs($this->teacher)->put($url, ['aspects' => [
            ['name' => 'Proyectos', 'type' => 'projects', 'weight' => '60'],
            ['name' => 'Examen', 'type' => 'direct', 'weight' => '30'],
        ]])->assertSessionHasErrors('aspects');

        $this->actingAs($this->teacher)->put($url, ['aspects' => [
            ['name' => 'Proyectos', 'type' => 'projects', 'weight' => '50'],
            ['name' => 'Otros proyectos', 'type' => 'projects', 'weight' => '50'],
        ]])->assertSessionHasErrors('aspects');

        $this->actingAs($this->teacher)->put($url, ['aspects' => [
            ['name' => 'Proyectos', 'type' => 'projects', 'weight' => '40'],
            ['name' => 'Examen', 'type' => 'direct', 'weight' => '35,5'],
            ['name' => 'Participación', 'type' => 'direct', 'weight' => '24.5'],
        ], 'subjects' => []])->assertSessionHasNoErrors();

        $this->assertSame(3, $this->group->termAspects()->where('campo', 'etica')->where('term', 1)->count());
    }

    public function test_aspects_belong_to_their_term(): void
    {
        $this->group->update(['current_term' => 2]);
        $this->assertFalse(TermBook::for($this->group->fresh())->isConfigured('lenguajes'));

        $this->actingAs($this->teacher)->post(route('campos.copy', [$this->group, 'lenguajes']))->assertRedirect();
        $this->assertSame(2, $this->group->termAspects()->where('term', 2)->where('campo', 'lenguajes')->count());
        $this->assertTrue(TermBook::for($this->group->fresh())->isConfigured('lenguajes'));
    }

    public function test_switching_term(): void
    {
        $this->actingAs($this->teacher)->from(route('campos.show', [$this->group, 'lenguajes']))
            ->post(route('term.switch', $this->group), ['term' => 3])
            ->assertRedirect(route('campos.index', $this->group));
        $this->assertSame(3, $this->group->fresh()->current_term);
    }

    public function test_project_and_product_forms(): void
    {
        $this->actingAs($this->teacher)->post(route('projects.store', $this->group), [
            'name' => 'Periódico', 'campo' => 'lenguajes', 'pdas' => ['PDA 1', '', 'PDA 2'],
        ])->assertSessionHasNoErrors();
        $project = $this->group->projects()->where('name', 'Periódico')->first();
        $this->assertSame(['PDA 1', 'PDA 2'], $project->pdas);

        $this->actingAs($this->teacher)->post(route('projects.store', $this->group), [
            'name' => 'X', 'campo' => 'lenguajes', 'pdas' => ['1', '2', '3', '4', '5'],
        ])->assertSessionHasErrors('pdas');

        $this->actingAs($this->teacher)->post(route('products.store', [$this->group, $project]), [
            'name' => 'Noticia', 'campo' => 'etica', 'instrument' => 'Rúbrica',
            'criteria' => [['description' => 'Informa un hecho', 'level_logrado' => 'Las 5 preguntas', 'level_apoyo' => 'No se comprende']],
        ])->assertSessionHasNoErrors();

        $criterion = $project->products()->first()->criteria()->first();
        $this->assertSame(['logrado' => 'Las 5 preguntas', 'apoyo' => 'No se comprende'], $criterion->descriptors());
    }

    public function test_excel_has_levels_matrix_and_headers(): void
    {
        $this->gradeAnaLenguajes();

        $response = $this->actingAs($this->teacher)->get(route('export.group', [$this->group, 'trimestre' => 1]));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        unlink($path);

        $this->assertSame(
            ['Resumen T1', 'Lenguajes T1', 'Saberes T1', 'Ética T1', 'De lo Humano T1', 'P · Germinación', 'Instrumentos', 'Pendientes'],
            $book->getSheetNames(),
        );

        // Lenguajes: Final 9 → columna "Satisfactorio" (la matriz de niveles).
        $lang = $book->getSheetByName('Lenguajes T1');
        $this->assertSame('Final', $lang->getCell('H5')->getValue());
        $this->assertSame('Satisfactorio', $lang->getCell('J5')->getValue());
        $this->assertEquals(9, $lang->getCell('H6')->getValue());
        $this->assertNull($lang->getCell('I6')->getValue());
        $this->assertEquals(9, $lang->getCell('J6')->getValue());

        // Proyecto: encabezado con PDA y la calificación 8 de Ana cae en "En proceso".
        $p = $book->getSheetByName('P · Germinación');
        $this->assertSame('PDA 1', $p->getCell('A7')->getValue());
        $this->assertSame('Observa el ciclo de vida', $p->getCell('C7')->getValue());
        $rows = $p->toArray(null, false, false, false);
        $levelRow = collect($rows)->search(fn ($r) => in_array('En proceso', $r, true));
        $anaRow = $rows[$levelRow + 1];
        $this->assertSame('Ana', $anaRow[1]);
        $this->assertEquals(10, $anaRow[2]);   // criterio 1 → Logrado
        $this->assertEquals(8, $anaRow[8]);    // criterio 2 → En proceso (C2: cols 7–10, En proceso = 9ª)

        $this->assertSame('Con las 5 preguntas', $book->getSheetByName('Instrumentos')->getCell('F2')->getValue());
        // Ana debe la bitácora (Saberes) y Educación Física; Bruno debe todo (2 criterios, examen, 2 materias, bitácora, Ed. Física).
        $pending = collect($book->getSheetByName('Pendientes')->toArray(null, false, false, false))->skip(1);
        $this->assertSame(['Bitácora', 'Educación Física'], $pending->where(4, 'Ana')->map(fn ($r) => str_contains($r[2], 'Bitácora') ? 'Bitácora' : $r[2])->values()->all());
        $this->assertSame(7, $pending->where(4, 'Bruno')->count());
    }

    public function test_main_screens_render(): void
    {
        $this->gradeAnaLenguajes();
        $this->actingAs($this->teacher);

        $this->get(route('grupos.show', $this->group))->assertOk()->assertSee('Campos formativos');
        $this->get(route('campos.index', $this->group))->assertOk()->assertSee('Lenguajes');
        $this->get(route('campos.show', [$this->group, 'lenguajes']))->assertOk()->assertSee('data-score-cell', false)->assertSee('Informe escrito');
        $this->get(route('campos.edit', [$this->group, 'lenguajes']))->assertOk()->assertSee('Materias adicionales');
        $this->get(route('campos.edit', [$this->group, 'humano']))->assertOk()->assertSee('Tareas y participación'); // estrategia sugerida
        $this->get(route('projects.index', $this->group))->assertOk()->assertSee('Germinación');
        $this->get(route('projects.show', [$this->group, $this->project]))->assertOk()->assertSee('Observa el ciclo de vida');
        $this->get(route('projects.create', $this->group))->assertOk();
        $this->get(route('products.create', [$this->group, $this->project]))->assertOk()->assertSee('Criterios a observar');
        $this->get(route('capture.criterion', [$this->group, $this->project, $this->letter, $this->c1]))->assertOk()->assertSee('Con las 5 preguntas');
        $this->get(route('capture.aspect', [$this->group, $this->exam]))->assertOk()->assertSee('id="keypad"', false);
        $this->get(route('capture.subject', [$this->group, $this->group->subjects()->first()]))->assertOk();
        $this->get(route('students.index', $this->group))->assertOk();
        $this->get(route('students.show', [$this->group, $this->ana]))->assertOk()->assertSee('Informe escrito');
    }
}
