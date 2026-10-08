<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Alta de maestras y cambio de contraseña desde la consola (Plesk → Laravel Toolkit → Artisan,
 * o SSH). Sustituye a "olvidé mi contraseña" mientras el servidor no tenga correo (SMTP).
 *
 *   php artisan maestra:cuenta ana@escuela.mx --nombre="Ana López"   → crea la cuenta
 *   php artisan maestra:cuenta ana@escuela.mx                         → ya existe: nueva contraseña
 *
 * La contraseña se genera al azar y se muestra UNA vez; también puede darse con --password.
 */
class TeacherAccount extends Command
{
    protected $signature = 'maestra:cuenta
        {email : Correo con el que la maestra entra}
        {--nombre= : Nombre a mostrar (obligatorio al crear)}
        {--password= : Contraseña a usar (mín. 8). Si no se da, se genera una}';

    protected $description = 'Crea la cuenta de una maestra o le asigna una contraseña nueva';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $password = $this->option('password') ?: $this->generatePassword();

        $v = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['password' => $password]);
            $this->info("Contraseña nueva para {$user->name} <{$email}>.");
        } else {
            $name = trim((string) $this->option('nombre'));
            if ($name === '') {
                $this->error('Para crear la cuenta indica --nombre="Nombre de la maestra".');

                return self::FAILURE;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $this->info("Cuenta creada: {$user->name} <{$email}>.");
        }

        if (! $this->option('password')) {
            $this->line("Contraseña: <comment>{$password}</comment>");
            $this->line('Anótala ahora: no se vuelve a mostrar.');
        }

        return self::SUCCESS;
    }

    /** Fácil de dictar: sin 0/O ni 1/l/I. */
    private function generatePassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';

        return collect(range(1, 10))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
    }
}
