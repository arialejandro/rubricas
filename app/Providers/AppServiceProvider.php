<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::resourceVerbs(['create' => 'nuevo', 'edit' => 'editar']);

        // Aislamiento entre maestras: un {group} ajeno responde 404, nunca se carga.
        Route::bind('group', function (string $value) {
            $user = auth()->user();
            abort_if($user === null, 404);

            return $user->groups()->findOrFail($value);
        });
    }
}
