<x-app-layout title="Users">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">Users</h1>
            <p class="text-sm text-gray-500">Super Admins, managers and field employees</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn-primary">+ Add user</a>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-col sm:flex-row gap-2" x-data>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or email" class="input">
        <select name="role" class="input sm:w-48" @change="$el.form.submit()">
            <option value="">All roles</option>
            @foreach (\App\Models\User::ROLE_LABELS as $k => $label)
                <option value="{{ $k }}" @selected(request('role') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Search</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach ($users as $u)
            <a href="{{ route('users.edit', $u) }}" class="flex items-center justify-between gap-3 p-3 hover:bg-gray-50">
                <div class="min-w-0">
                    <p class="font-medium truncate">{{ $u->name }} @unless($u->is_active)<span class="ml-1 text-xs rounded bg-red-100 text-red-700 px-1.5 py-0.5">Inactive</span>@endunless</p>
                    <p class="text-xs text-gray-500 truncate">{{ $u->email }}@if($u->manager) · reports to {{ $u->manager->name }}@endif @if($u->territory) · {{ $u->territory }}@endif</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-xs rounded-full px-2 py-0.5 {{ $u->isSuperAdmin() ? 'bg-purple-100 text-purple-800' : ($u->isManager() ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-700') }}">{{ $u->roleLabel() }}</span>
                    <p class="text-xs text-gray-400 mt-1">{{ $u->onboarded_dealers_count }} onboarded</p>
                </div>
            </a>
        @endforeach
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
