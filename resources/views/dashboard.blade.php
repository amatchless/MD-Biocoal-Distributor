@php
    $user = auth()->user();
    $s = $stats['by_status'];
    $tiles = [
        ['label' => $user->isEmployee() ? 'Onboarded by me' : 'Total onboarded', 'value' => $stats['total'], 'sub' => $stats['dealers'].' '.\Illuminate\Support\Str::plural('dealer', $stats['dealers']).' · '.$stats['distributors'].' '.\Illuminate\Support\Str::plural('distributor', $stats['distributors'])],
        ['label' => 'This month', 'value' => $stats['this_month'], 'sub' => $stats['last_7_days'].' in the last 7 days'],
        ['label' => 'Awaiting review', 'value' => ($s['submitted'] ?? 0) + ($s['under_review'] ?? 0), 'sub' => ($s['needs_info'] ?? 0).' sent back for info', 'href' => route('dealers.index', ['status' => 'submitted'])],
        ['label' => 'Approved', 'value' => $s['approved'] ?? 0, 'sub' => ($s['rejected'] ?? 0).' rejected', 'href' => route('dealers.index', ['status' => 'approved'])],
    ];
@endphp

<x-app-layout title="Dashboard">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Hello, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
                <p class="text-sm text-gray-500">
                    @if ($user->position){{ $user->position->label() }} · @endif{{ $user->roleLabel() }}@if ($user->manager) · reports to {{ $user->manager->name }}@endif
                    @if ($user->isManager()) · team of {{ $team->where(fn ($m) => $m->manager_id === $user->id)->count() }}@endif
                </p>
            </div>
            <a href="{{ route('dealers.create') }}" class="btn-primary hidden sm:inline-flex">+ Onboard</a>
        </div>

        <a href="{{ route('dealers.create') }}" class="sm:hidden flex items-center justify-between rounded-xl bg-brand-700 text-white p-4 shadow">
            <span>
                <span class="block font-semibold text-lg">Onboard a dealer</span>
                <span class="block text-sm text-brand-100">GPS, documents, policy & signature</span>
            </span>
            <span class="text-3xl leading-none">+</span>
        </a>

        @include('attendance._clock')

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($tiles as $t)
                <a href="{{ $t['href'] ?? route('dealers.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-brand-300">
                    <p class="text-xs text-gray-500">{{ $t['label'] }}</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($t['value']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $t['sub'] }}</p>
                </a>
            @endforeach
        </div>

        @if ($needsAttention->isNotEmpty())
            <x-card :title="$user->isEmployee() ? 'Sent back to you for more information' : 'Waiting for review'" :subtitle="$user->isEmployee() ? 'Open each one, fix what the manager asked for and save.' : 'Oldest first'">
                <ul class="divide-y divide-gray-100">
                    @foreach ($needsAttention as $d)
                        <li>
                            <a href="{{ route($user->isEmployee() ? 'dealers.edit' : 'dealers.show', $d) }}" class="py-2 flex items-center justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block font-medium truncate">{{ $d->business_name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ $d->city }} · {{ local_time($d->created_at, 'd M') }}@unless($user->isEmployee()) · {{ $d->creator?->name }}@endunless
                                        @if ($user->isEmployee() && $d->review_remarks) · “{{ \Illuminate\Support\Str::limit($d->review_remarks, 60) }}”@endif
                                    </span>
                                </span>
                                <x-status-badge :status="$d->status" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if (! $user->isEmployee())
            <x-card title="Team performance" :subtitle="$user->isSuperAdmin() ? 'All employees' : 'Employees reporting to you'">
                @if ($team->isEmpty())
                    <p class="text-sm text-gray-500">No employees assigned yet.@if($user->isSuperAdmin()) <a class="text-brand-700 underline" href="{{ route('users.create') }}">Add one</a>.@endif</p>
                @else
                    <div class="overflow-x-auto -mx-4 px-4">
                        <table class="w-full text-sm min-w-[520px]">
                            <thead class="text-left text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 font-medium">Employee</th>
                                    <th class="py-2 font-medium text-right">Total</th>
                                    <th class="py-2 font-medium text-right">This month</th>
                                    <th class="py-2 font-medium text-right">Pending</th>
                                    <th class="py-2 font-medium text-right">Approved</th>
                                    <th class="py-2 font-medium text-right">Last onboarding</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($team as $m)
                                    <tr>
                                        <td class="py-2">
                                            <a href="{{ route('dealers.index', ['employee' => $m->id]) }}" class="font-medium hover:text-brand-700">{{ $m->name }}</a> <x-position-badge :position="$m->position" class="ml-1" />
                                            <div class="text-xs text-gray-400">{{ $m->roleLabel() }}@if ($user->isSuperAdmin() && $m->manager) · {{ $m->manager->name }}@endif @if($m->territory) · {{ $m->territory }}@endif</div>
                                        </td>
                                        <td class="py-2 text-right font-semibold">{{ $m->stats['total'] }}</td>
                                        <td class="py-2 text-right">{{ $m->stats['this_month'] }}</td>
                                        <td class="py-2 text-right">{{ $m->stats['pending'] + $m->stats['needs_info'] }}</td>
                                        <td class="py-2 text-right">{{ $m->stats['approved'] }}</td>
                                        <td class="py-2 text-right text-gray-500 whitespace-nowrap">{{ $m->stats['last_at'] ? local_time($m->stats['last_at'], 'd M Y') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif

        @if ($mapPoints->isNotEmpty())
            <x-card title="Onboarding map" subtitle="Where each dealer / distributor was onboarded (latest 200)">
                <div id="dealer-map" class="h-72 sm:h-96 rounded-lg border border-gray-200 z-0"></div>
            </x-card>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
            <script>
                (function () {
                    if (!window.L) return;
                    const points = @json($mapPoints);
                    const map = L.map('dealer-map', { scrollWheelZoom: false });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                    const markers = points.map((p) => L.marker([p.lat, p.lng]).bindPopup(`<a href="${p.url}"><strong>${esc(p.name)}</strong></a><br>${esc(p.ref)} · ${esc(p.status)}`));
                    const group = L.featureGroup(markers).addTo(map);
                    map.fitBounds(group.getBounds().pad(0.2), { maxZoom: 14 });
                })();
            </script>
        @endif

        <div class="grid md:grid-cols-2 gap-4">
            <x-card title="Recent onboardings">
                <x-slot name="actions"><a href="{{ route('dealers.index') }}" class="text-sm text-brand-700">View all</a></x-slot>
                @if ($recent->isEmpty())
                    <p class="text-sm text-gray-500">Nothing yet. Tap <strong>Onboard</strong> when you're at a dealer's premises.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($recent as $d)
                            <li>
                                <a href="{{ route('dealers.show', $d) }}" class="py-2 flex items-center justify-between gap-3">
                                    <span class="min-w-0">
                                        <span class="block font-medium truncate">{{ $d->business_name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $d->typeLabel() }} · {{ $d->city }} · {{ local_time($d->created_at, 'd M') }}@unless($user->isEmployee()) · {{ $d->creator?->name }}@endunless</span>
                                    </span>
                                    <x-status-badge :status="$d->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Licence renewals" subtitle="Expired or expiring within 60 days">
                @if ($expiring->isEmpty())
                    <p class="text-sm text-gray-500">No licences due for renewal.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($expiring as $d)
                            @foreach ($d->expiringLicences() as $lic)
                                <li class="py-2 flex items-center justify-between gap-3">
                                    <a href="{{ route('dealers.show', $d) }}" class="min-w-0">
                                        <span class="block font-medium truncate">{{ $d->business_name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $lic['kind'] }} licence</span>
                                    </a>
                                    <span class="text-xs font-medium whitespace-nowrap {{ $lic['expired'] ? 'text-red-700' : 'text-amber-700' }}">{{ $lic['expired'] ? 'Expired' : 'Expires' }} {{ $lic['expiry']->format('d M Y') }}</span>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
