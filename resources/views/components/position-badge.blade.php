@props(['position'])
@if ($position)
    <span {{ $attributes->merge(['class' => 'inline-block text-xs font-medium rounded bg-amber-50 text-amber-800 border border-amber-200 px-1.5 py-0.5']) }} title="{{ $position->name }}">{{ $position->abbreviation }}</span>
@endif
