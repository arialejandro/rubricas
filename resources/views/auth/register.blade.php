<x-layouts.app title="Crear cuenta">
    <div class="mx-auto mt-6 max-w-sm sm:mt-16">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid size-16 place-items-center rounded-2xl bg-primary text-on-primary"><x-icon name="clipboard" class="size-8" /></div>
            <h1 class="text-2xl font-bold">Crear cuenta</h1>
        </div>

        <form method="POST" action="{{ route('register') }}" class="card space-y-4 p-6">
            @csrf
            <div>
                <label class="label" for="name">Nombre</label>
                <input class="input" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>
            <div>
                <label class="label" for="email">Correo</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </div>
            <div>
                <label class="label" for="password">Contraseña <span class="font-normal text-ink-muted">(mín. 8)</span></label>
                <input class="input" id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="password_confirmation">Repite la contraseña</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary w-full">Crear cuenta</button>
        </form>

        <p class="mt-4 text-center text-sm text-ink-muted">¿Ya tienes cuenta? <a class="font-semibold text-primary" href="{{ route('login') }}">Entrar</a></p>
    </div>
</x-layouts.app>
