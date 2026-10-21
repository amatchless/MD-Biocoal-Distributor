@php use App\Models\EmployeeOnboarding; @endphp
<x-app-layout title="New hires">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">New hires</h1>
            <p class="text-sm text-gray-500">Employee onboarding and background verification.</p>
        </div>
        <a href="{{ route('onboarding.create') }}" class="btn-primary py-2 whitespace-nowrap">+ New hire</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @foreach ([
            ['label' => 'With candidate', 'value' => ($counts['invited'] ?? 0) + ($counts['info_pending'] ?? 0), 'href' => route('onboarding.index', ['status' => 'invited'])],
            ['label' => 'To verify', 'value' => $counts['under_verification'] ?? 0, 'href' => route('onboarding.index', ['status' => 'under_verification'])],
            ['label' => 'Awaiting decision', 'value' => $counts['risk_review'] ?? 0, 'href' => route('onboarding.index', ['status' => 'risk_review'])],
            ['label' => 'Overdue', 'value' => $overdue, 'href' => route('onboarding.index', ['overdue' => 1])],
        ] as $t)
            <a href="{{ $t['href'] }}" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-brand-300">
                <p class="text-xs text-gray-500">{{ $t['label'] }}</p>
                <p class="text-3xl font-bold mt-1 {{ $t['label'] === 'Overdue' && $t['value'] ? 'text-red-700' : '' }}">{{ number_format($t['value']) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-3 mb-4 grid grid-cols-2 md:grid-cols-6 gap-2" x-data>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, ref, phone, PAN…" class="input col-span-2">
        <select name="status" class="input" @change="$el.form.submit()">
            <option value="">All statuses</option>
            @foreach (EmployeeOnboarding::STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="department" class="input" @change="$el.form.submit()">
            <option value="">All departments</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="owner" class="input" @change="$el.form.submit()">
            <option value="">All case owners</option>
            @foreach ($owners as $o)
                <option value="{{ $o->id }}" @selected(($filters['owner'] ?? '') == $o->id)>{{ $o->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="btn-primary flex-1">Filter</button>
            @if (array_filter($filters))<a href="{{ route('onboarding.index') }}" class="btn-secondary">Clear</a>@endif
        </div>
    </form>

    @if ($onboardings->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <p class="text-gray-600">{{ array_filter($filters) ? 'Nothing matches these filters.' : 'No new hires yet.' }}</p>
            <a href="{{ route('onboarding.create') }}" class="btn-primary mt-4">Start the first onboarding</a>
        </div>
    @else
        <ul class="md:hidden space-y-2">
            @foreach ($onboardings as $o)
                <li>
                    <a href="{{ route('onboarding.show', $o) }}" class="block bg-white rounded-xl border border-gray-200 p-3 active:bg-gray-50">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ $o->candidate_name }}</p>
                                <p class="text-xs text-gray-500">{{ $o->department?->name }}@if($o->position) · {{ $o->position->label() }}@endif</p>
                            </div>
                            <x-onboarding-status :status="$o->status" />
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $o->ref_no }}@if($o->target_date) · due {{ $o->target_date->format('d M') }}@endif @if($o->isOverdue())<span class="text-red-600">overdue</span>@endif</p>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="hidden md:block bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Candidate</th>
                        <th class="px-4 py-2 font-medium">Department / position</th>
                        <th class="px-4 py-2 font-medium">Case owner</th>
                        <th class="px-4 py-2 font-medium">Joining</th>
                        <th class="px-4 py-2 font-medium">Due</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($onboardings as $o)
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="location.href='{{ route('onboarding.show', $o) }}'">
                            <td class="px-4 py-2">
                                <a href="{{ route('onboarding.show', $o) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $o->candidate_name }}</a>
                                <div class="text-xs text-gray-400 font-mono">{{ $o->ref_no }}</div>
                            </td>
                            <td class="px-4 py-2">{{ $o->department?->name }}<div class="text-xs text-gray-400">{{ $o->position?->label() }}</div></td>
                            <td class="px-4 py-2">{{ $o->owner?->name ?? '—' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $o->joining_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap {{ $o->isOverdue() ? 'text-red-700 font-medium' : '' }}">{{ $o->target_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2"><x-onboarding-status :status="$o->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $onboardings->links() }}</div>
    @endif
</x-app-layout>
