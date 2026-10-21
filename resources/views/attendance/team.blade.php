@php $user = auth()->user(); @endphp
<x-app-layout :title="$user->isSuperAdmin() ? 'All attendance' : 'Team attendance'">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">{{ $user->isSuperAdmin() ? 'All attendance' : 'Team attendance' }}</h1>
            <p class="text-sm text-gray-500">
                {{ $user->isSuperAdmin() ? 'Field attendance across all departments.' : 'You and the employees reporting to you.' }}
                {{ $clockedInNow }} clocked in right now.
            </p>
        </div>
        <a href="{{ route('attendance.index') }}" class="btn-secondary py-2 whitespace-nowrap">My attendance</a>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-3 mb-4 grid grid-cols-2 md:grid-cols-6 gap-2" x-data>
        <label class="text-sm text-gray-500">From <input type="date" name="from" value="{{ $filters['from'] }}" class="input"></label>
        <label class="text-sm text-gray-500">To <input type="date" name="to" value="{{ $filters['to'] }}" class="input"></label>
        <label class="text-sm text-gray-500 col-span-2 md:col-span-1">Employee
            <select name="employee" class="input" @change="$el.form.submit()">
                <option value="">Everyone</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->id }}" @selected(($filters['employee'] ?? '') == $e->id)>{{ $e->name }}</option>
                @endforeach
            </select>
        </label>
        @if ($departments->isNotEmpty())
            <label class="text-sm text-gray-500 col-span-2 md:col-span-1">Department
                <select name="department" class="input" @change="$el.form.submit()">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="col-span-2 md:col-span-{{ $departments->isNotEmpty() ? 2 : 3 }} flex gap-2 items-end">
            <button class="btn-primary flex-1">Filter</button>
            @if (request()->hasAny(['from', 'to', 'employee', 'department']))<a href="{{ route('attendance.team') }}" class="btn-secondary">Clear</a>@endif
        </div>
    </form>

    @if ($days->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-600">No attendance marked in this period.</div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">In</th>
                        <th class="px-4 py-2 font-medium">Out</th>
                        <th class="px-4 py-2 font-medium text-right">Worked</th>
                        <th class="px-4 py-2 font-medium">Punches</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 align-top">
                    @foreach ($days as $day)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $day['date']->format('D, d M') }}</td>
                            <td class="px-4 py-2">
                                <span class="font-medium">{{ $day['user']?->name }}</span> <x-position-badge :position="$day['user']?->position" class="ml-1" />
                                @if ($user->isSuperAdmin() && $day['user']?->department)<div class="text-xs text-gray-400">{{ $day['user']->department->name }}</div>@endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $day['first_in'] ? local_time($day['first_in']->created_at, 'h:i A') : '—' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($day['open'])<span class="text-amber-700">Still in</span>@else{{ $day['last_out'] ? local_time($day['last_out']->created_at, 'h:i A') : '—' }}@endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">@include('attendance._minutes', ['minutes' => $day['minutes']])</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($day['punches'] as $p)
                                        @include('attendance._punch', ['p' => $p])
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $days->links() }}</div>
    @endif
</x-app-layout>
