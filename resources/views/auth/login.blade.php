<x-layouts.app title="Entrar">
    <div class="mx-auto mt-6 max-w-sm sm:mt-16">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid size-16 place-items-center rounded-2xl bg-primary text-on-primary"><x-icon name="clipboard" class="size-8" /></div>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-ink-muted">Proyectos, rúbricas y calificaciones</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-6">
            @csrf
            <div>
                <label class="label" for="email">Correo</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div>
                <label class="label" for="password">Contraseña</label>
                <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-full">Entrar</button>
        </form>

        @if (config('rubrica.registration'))
            <p class="mt-4 text-center text-sm text-ink-muted">¿No tienes cuenta? <a class="font-semibold text-primary" href="{{ route('register') }}">Crear cuenta</a></p>
        @endif

        <button type="button" class="btn btn-ghost mx-auto mt-6 flex" data-theme-toggle aria-label="Cambiar modo claro u oscuro">
            <x-icon name="moon" class="size-4 dark:hidden" /><x-icon name="sun" class="hidden size-4 dark:block" /> Modo claro / oscuro
        </button>
    </div>
</x-layouts.app>
