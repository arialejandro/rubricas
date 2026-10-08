{{-- Selector de trimestre del grupo (se guarda en el grupo: igual en iPad y computadora). --}}
@props(['group'])
<form method="POST" action="{{ route('term.switch', $group) }}" {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    @csrf
    <span class="text-sm font-semibold text-ink-muted">Trimestre</span>
    <div class="flex gap-1 rounded-xl border border-line bg-surface p-1" role="group" aria-label="Trimestre">
        @foreach (\App\Support\Campos::TERMS as $t)
            <button name="term" value="{{ $t }}" @class(['grid min-h-10 min-w-11 place-items-center rounded-lg text-sm font-bold transition',
                'bg-primary text-on-primary shadow-sm' => $group->term() === $t, 'text-ink-muted hover:bg-surface-2 hover:text-ink' => $group->term() !== $t])
                @if ($group->term() === $t) aria-current="true" @endif aria-label="Trimestre {{ $t }}">{{ $t }}</button>
        @endforeach
    </div>
</form>
