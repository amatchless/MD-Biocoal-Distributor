@php $user = auth()->user(); @endphp
<x-app-layout :title="$user->isEmployee() ? 'My onboardings' : 'Dealers & distributors'">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">{{ $user->isEmployee() ? 'My onboardings' : 'Dealers & distributors' }}</h1>
            <p class="text-sm text-gray-500">
                {{ $dealers->total() }} {{ \Illuminate\Support\Str::plural('record', $dealers->total()) }}
                @if ($user->isManager()) onboarded by you and your team @elseif ($user->isEmployee()) onboarded by you @endif
            </p>
        </div>
        <a href="{{ route('dealers.export', request()->query()) }}" class="btn-secondary py-2">Export CSV</a>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-3 mb-4 grid grid-cols-2 md:grid-cols-6 gap-2" x-data>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, GSTIN, PAN, phone, town…" class="input col-span-2 md:col-span-2">
        <select name="status" class="input" @change="$el.form.submit()">
            <option value="">All statuses</option>
            @foreach (\App\Models\Dealer::STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="type" class="input" @change="$el.form.submit()">
            <option value="">Dealers & distributors</option>
            @foreach (\App\Models\Dealer::TYPES as $k => $label)
                <option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $label }}s</option>
            @endforeach
        </select>
        @if ($employees->isNotEmpty())
            <select name="employee" class="input col-span-2 md:col-span-1" @change="$el.form.submit()">
                <option value="">All employees</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->id }}" @selected(($filters['employee'] ?? '') == $e->id)>{{ $e->name }}</option>
                @endforeach
            </select>
        @endif
        <div class="col-span-2 md:col-span-1 flex gap-2">
            <button class="btn-primary flex-1">Filter</button>
            @if (array_filter($filters))<a href="{{ route('dealers.index') }}" class="btn-secondary">Clear</a>@endif
        </div>
        <details class="col-span-2 md:col-span-6 text-sm" @if(!empty($filters['from']) || !empty($filters['to'])) open @endif>
            <summary class="text-gray-500 cursor-pointer">Date range</summary>
            <div class="grid grid-cols-2 gap-2 mt-2">
                <label>From <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></label>
                <label>To <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></label>
            </div>
        </details>
    </form>

    @if ($dealers->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <p class="text-gray-600">{{ array_filter($filters) ? 'Nothing matches these filters.' : 'No dealers or distributors onboarded yet.' }}</p>
            <a href="{{ route('dealers.create') }}" class="btn-primary mt-4">Onboard the first one</a>
        </div>
    @else
        @include('dealers._list', ['dealers' => $dealers, 'showCreator' => ! $user->isEmployee()])
        <div class="mt-4">{{ $dealers->links() }}</div>
    @endif
</x-app-layout>
