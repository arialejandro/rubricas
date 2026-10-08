@props(['graded' => 0, 'total' => 0, 'live' => false])
@php($pct = $total > 0 ? (int) floor($graded * 100 / $total) : 0)
<div {{ $attributes->merge(['class' => 'h-2.5 w-full overflow-hidden rounded-full bg-slate-200']) }}>
    <div @if ($live) data-project-bar @endif
         class="h-full rounded-full transition-all {{ $total > 0 && $graded >= $total ? 'bg-emerald-500' : 'bg-brand-600' }}"
         style="width: {{ $pct }}%"></div>
</div>
