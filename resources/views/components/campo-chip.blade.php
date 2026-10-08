@props(['campo', 'short' => true])
<span {{ $attributes->merge(['class' => 'campo-chip']) }} style="--c: {{ \App\Support\Campos::color($campo) }}">
    <span class="size-2 rounded-full bg-current"></span>{{ $short ? \App\Support\Campos::short($campo) : \App\Support\Campos::name($campo) }}
</span>
