{{-- Field inside the onboarding form: shows server errors returned to the Alpine component. --}}
@props(['label', 'name', 'required' => false, 'hint' => null])
<div {{ $attributes }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
    </label>
    <div class="mt-1">{{ $slot }}</div>
    @if ($hint)<p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>@endif
    <p class="mt-1 text-sm text-red-600" x-show="err('{{ $name }}')" x-text="err('{{ $name }}')" x-cloak></p>
</div>
