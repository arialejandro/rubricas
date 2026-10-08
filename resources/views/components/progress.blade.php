@props(['value' => 0, 'project' => null, 'criterion' => null])
<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-surface-2']) }} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}">
    <div @if ($project) data-project-bar="{{ $project }}" @endif @if ($criterion) data-criterion-bar="{{ $criterion }}" @endif
         class="h-full rounded-full transition-[width] duration-300 {{ $value >= 100 ? 'bg-done' : 'bg-primary' }}"
         style="width: {{ $value }}%"></div>
</div>
