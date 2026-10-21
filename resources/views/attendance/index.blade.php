@php $user = auth()->user(); @endphp
<x-app-layout title="Attendance">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">Attendance</h1>
            <p class="text-sm text-gray-500">Clock in when you start field work and clock out when you finish.</p>
        </div>
        @if ($user->canViewTeamAttendance())
            <a href="{{ route('attendance.team') }}" class="btn-secondary py-2 whitespace-nowrap">{{ $user->seesAllAttendance() ? 'All attendance' : 'Team attendance' }}</a>
        @endif
    </div>

    <div class="grid md:grid-cols-5 gap-4 items-start">
        <div class="md:col-span-2">
            @include('attendance._clock')
        </div>

        <x-card title="Last 30 days" subtitle="Your clock ins and outs, newest first" class="md:col-span-3">
            @if ($days->isEmpty())
                <p class="text-sm text-gray-500">No attendance marked in the last 30 days.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($days as $day)
                        <li class="py-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-medium">{{ $day['date']->format('D, d M') }}</span>
                                <span class="text-sm text-gray-500">
                                    @if ($day['open'] && $day['minutes'] === 0)
                                        <span class="text-amber-700">No clock out yet</span>
                                    @else
                                        @include('attendance._minutes', ['minutes' => $day['minutes']])
                                        @if ($day['open']) <span class="text-amber-700">· no clock out</span>@endif
                                    @endif
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($day['punches'] as $p)
                                    @include('attendance._punch', ['p' => $p])
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
