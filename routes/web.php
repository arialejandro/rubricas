<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StudentController;
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
        Route::get('alumnos', [StudentController::class, 'index'])->name('students.index');
        Route::post('alumnos', [StudentController::class, 'store'])->name('students.store');
        Route::post('alumnos/importar', [StudentController::class, 'import'])->name('students.import');
        Route::get('alumnos/plantilla', [StudentController::class, 'template'])->name('students.template');
        Route::get('alumnos/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::put('alumnos/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('alumnos/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::resource('proyectos', ProjectController::class)
            ->parameters(['proyectos' => 'project'])
            ->names('projects');

        Route::get('proyectos/{project}/capturar/{criterion}', CaptureController::class)->name('capture');
        Route::put('proyectos/{project}/calificaciones', [GradeController::class, 'update'])->name('grades.update');

        Route::get('excel', [ExportController::class, 'group'])->name('export.group');
        Route::get('proyectos/{project}/excel', [ExportController::class, 'project'])->name('export.project');
    });
});
