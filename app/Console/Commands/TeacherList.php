<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lista las cuentas que existen (para recordar con qué correo se entra).
 * Las contraseñas no se pueden ver (están cifradas): se cambian con `maestra:cuenta correo`.
 */
class TeacherList extends Command
{
    protected $signature = 'maestra:lista';

    protected $description = 'Muestra las cuentas de maestras (nombre, correo y sus grupos)';

    public function handle(): int
    {
        $users = User::with('groups')->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->warn('No hay cuentas. Crea una con: php artisan maestra:cuenta correo@escuela.mx --nombre="Nombre"');

            return self::SUCCESS;
        }

        $this->table(['Nombre', 'Correo', 'Grupos'], $users->map(fn ($u) => [
            $u->name,
            $u->email,
            $u->groups->map(fn ($g) => $g->label().' '.$g->shiftLabel())->implode(', ') ?: '—',
        ]));
        $this->line('Para poner contraseña nueva: php artisan maestra:cuenta CORREO');

        return self::SUCCESS;
    }
}
