{{-- One clock-in / clock-out chip: time, map link, photo and note. --}}
<span class="inline-flex flex-wrap items-center gap-x-2 gap-y-0.5 rounded-lg border px-2 py-1 text-xs {{ $p->type === 'in' ? 'border-brand-200 bg-brand-50' : 'border-amber-200 bg-amber-50' }}">
    <span class="font-semibold {{ $p->type === 'in' ? 'text-brand-800' : 'text-amber-800' }}">{{ $p->type === 'in' ? 'In' : 'Out' }} {{ local_time($p->created_at, 'h:i A') }}</span>
    <a href="{{ $p->mapUrl() }}" target="_blank" rel="noopener" class="text-brand-700 underline">Map</a>
    @if ($p->photo_path)
        <a href="{{ route('attendance.photo', $p) }}" target="_blank" class="text-brand-700 underline">Photo</a>
    @endif
    @if ($p->notes)
        <span class="text-gray-600 basis-full">“{{ $p->notes }}”</span>
    @endif
</span>
