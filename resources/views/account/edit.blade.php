<x-layouts.app title="Mi cuenta" hide-term>
    <div class="mx-auto max-w-lg space-y-6">
        <h1 class="text-2xl font-bold sm:text-3xl">Mi cuenta</h1>

        <form method="POST" action="{{ route('account.password') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold">Cambiar contraseña</h2>
            <div>
                <label class="label" for="current_password">Contraseña actual</label>
                <input class="input" id="current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div>
                <label class="label" for="password">Contraseña nueva <span class="font-normal text-ink-muted">(mín. 8)</span></label>
                <input class="input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="password_confirmation">Repite la contraseña nueva</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-primary w-full">Cambiar contraseña</button>
        </form>

        <form method="POST" action="{{ route('account.update') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold">Mis datos</h2>
            <div>
                <label class="label" for="name">Nombre</label>
                <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
            </div>
            <div>
                <label class="label" for="email">Correo (con el que entras)</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
            </div>
            <button class="btn btn-ghost w-full">Guardar datos</button>
        </form>
    </div>
</x-layouts.app>
