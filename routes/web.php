<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampoController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TermController;
use App\Support\Campos;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('registro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('registro', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // {group} solo resuelve grupos de la maestra en sesión y lo marca como turno activo
    // (ver AppServiceProvider); scopeBindings obliga a que lo anidado pertenezca a ese grupo.
    Route::resource('grupos', GroupController::class)->parameters(['grupos' => 'group'])->except('index');

    Route::scopeBindings()->prefix('grupos/{group}')->group(function () {
        Route::post('trimestre', TermController::class)->name('term.switch');

        // Campos formativos del trimestre activo
        Route::whereIn('campo', Campos::keys())->group(function () {
            Route::get('campos', [CampoController::class, 'index'])->name('campos.index');
            Route::get('campos/{campo}', [CampoController::class, 'show'])->name('campos.show');
            Route::get('campos/{campo}/configurar', [CampoController::class, 'edit'])->name('campos.edit');
            Route::put('campos/{campo}', [CampoController::class, 'update'])->name('campos.update');
            Route::post('campos/{campo}/copiar', [CampoController::class, 'copyPrevious'])->name('campos.copy');
        });

        // Captura alumno por alumno (teclado) + guardado de una celda
        Route::get('capturar/aspecto/{termAspect}', [CaptureController::class, 'aspect'])->name('capture.aspect');
        Route::get('capturar/materia/{subject}', [CaptureController::class, 'subject'])->name('capture.subject');
        Route::get('proyectos/{project}/productos/{product}/criterios/{criterion}', [CaptureController::class, 'criterion'])->name('capture.criterion');
        Route::put('calificar', ScoreController::class)->name('score.update');

        Route::resource('proyectos', ProjectController::class)->parameters(['proyectos' => 'project'])->names('projects');
        Route::resource('proyectos.productos', ProductController::class)
            ->parameters(['proyectos' => 'project', 'productos' => 'product'])
            ->except(['index', 'show'])
            ->names('products');

        Route::get('alumnos', [StudentController::class, 'index'])->name('students.index');
        Route::post('alumnos', [StudentController::class, 'store'])->name('students.store');
        Route::post('alumnos/importar', [StudentController::class, 'import'])->name('students.import');
        Route::get('alumnos/plantilla', [StudentController::class, 'template'])->name('students.template');
        Route::get('alumnos/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::put('alumnos/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('alumnos/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::get('excel', ExportController::class)->name('export.group');
    });
});
