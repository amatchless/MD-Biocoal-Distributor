{{-- A labelled form field. Pass the input as the slot; errors for `name` are shown below it. --}}
@props(['label', 'name', 'required' => false, 'hint' => null])
<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
    </label>
    <div class="mt-1">{{ $slot }}</div>
    @if ($hint)<p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>@endif
    @php $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
    @error($key)<p class="mt-1 text-sm text-red-600" data-error>{{ $message }}</p>@enderror
</div>
