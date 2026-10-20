@props(['title' => null, 'subtitle' => null])
<section {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-200']) }}>
    @if ($title)
        <div class="px-4 pt-4 pb-2 flex items-start justify-between gap-2">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $title }}</h3>
                @if ($subtitle)<p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            {{ $actions ?? '' }}
        </div>
    @endif
    <div class="{{ $title ? 'px-4 pb-4' : 'p-4' }}">
        {{ $slot }}
    </div>
</section>
