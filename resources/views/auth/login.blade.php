<x-layouts.app title="Entrar">
    <div class="mx-auto mt-8 max-w-sm sm:mt-16">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid size-14 place-items-center rounded-2xl bg-brand-900 text-2xl text-white">✓</div>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-sm text-slate-500">Proyectos, rúbricas y calificaciones</p>
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
            <p class="mt-4 text-center text-sm text-slate-600">¿No tienes cuenta? <a class="font-semibold text-brand-700" href="{{ route('register') }}">Crear cuenta</a></p>
        @endif
    </div>
</x-layouts.app>
