{{-- Mobile cards; table on wider screens. Expects $dealers and $showCreator. --}}
<ul class="md:hidden space-y-2">
    @foreach ($dealers as $d)
        <li>
            <a href="{{ route('dealers.show', $d) }}" class="block bg-white rounded-xl border border-gray-200 p-3 active:bg-gray-50">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $d->business_name }}</p>
                        <p class="text-xs text-gray-500">{{ $d->typeLabel() }} · {{ $d->city }}, {{ $d->district }}</p>
                    </div>
                    <x-status-badge :status="$d->status" />
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ $d->ref_no }} · {{ local_time($d->created_at, 'd M Y') }}@if($showCreator) · {{ $d->creator?->name }}@endif</p>
            </a>
        </li>
    @endforeach
</ul>

<div class="hidden md:block bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-2 font-medium">Business</th>
                <th class="px-4 py-2 font-medium">Type</th>
                <th class="px-4 py-2 font-medium">Location</th>
                @if ($showCreator)<th class="px-4 py-2 font-medium">Onboarded by</th>@endif
                <th class="px-4 py-2 font-medium">Date</th>
                <th class="px-4 py-2 font-medium">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($dealers as $d)
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="location.href='{{ route('dealers.show', $d) }}'">
                    <td class="px-4 py-2">
                        <a href="{{ route('dealers.show', $d) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $d->business_name }}</a>
                        <div class="text-xs text-gray-400 font-mono">{{ $d->ref_no }}</div>
                    </td>
                    <td class="px-4 py-2">{{ $d->typeLabel() }}</td>
                    <td class="px-4 py-2">{{ $d->city }}, {{ $d->district }}<div class="text-xs text-gray-400">{{ $d->state }}</div></td>
                    @if ($showCreator)<td class="px-4 py-2">{{ $d->creator?->name }}</td>@endif
                    <td class="px-4 py-2 whitespace-nowrap">{{ local_time($d->created_at, 'd M Y') }}</td>
                    <td class="px-4 py-2"><x-status-badge :status="$d->status" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
