<?php

namespace App\Providers;

use App\Models\Group;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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
        // El grupo visitado queda como turno activo: "/" vuelve a él.
        Route::bind('group', function (string $value) {
            $user = auth()->user();
            abort_if($user === null, 404);

            $group = $user->groups()->findOrFail($value);
            session(['group_id' => $group->id]);

            return $group;
        });

        // Barra superior: grupo actual + selector de turno (máx. 2 grupos por maestra).
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            if ($user === null) {
                return;
            }

            $groups = $user->groups()->get()->keyBy('shift');
            $current = request()->route('group');
            if (! $current instanceof Group) {
                $current = $groups->firstWhere('id', session('group_id')) ?? $groups->first();
            }

            $view->with(['navGroups' => $groups, 'navGroup' => $current]);
        });
    }
}
