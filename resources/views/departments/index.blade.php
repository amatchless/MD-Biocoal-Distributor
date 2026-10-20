<x-app-layout title="Departments">
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <h1 class="text-xl font-bold">Departments</h1>
            <p class="text-sm text-gray-500">{{ $departments->count() }} departments · assign people from the Users page</p>
        </div>
        <a href="{{ route('users.index') }}" class="btn-secondary">Users</a>
    </div>

    @if ($unassigned)
        <a href="{{ route('users.index', ['department' => 'none']) }}" class="block mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 hover:bg-amber-100">
            {{ $unassigned }} {{ Str::plural('user', $unassigned) }} not in a department yet →
        </a>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 items-start">
        @foreach ($departments as $d)
            <div class="bg-white rounded-xl border border-gray-200" x-data="{ open: false }">
                <button type="button" class="w-full flex items-center justify-between gap-3 p-3 text-left" @click="open = ! open" @disabled($d->users_count === 0)>
                    <span class="font-medium">{{ $d->name }}</span>
                    <span class="text-xs rounded-full px-2 py-0.5 {{ $d->users_count ? 'bg-brand-50 text-brand-800' : 'bg-gray-100 text-gray-500' }}">{{ $d->users_count }} {{ Str::plural('person', $d->users_count) }}</span>
                </button>
                @if ($d->users_count)
                    <div x-show="open" x-cloak class="border-t border-gray-100 divide-y divide-gray-100">
                        @foreach ($d->users as $u)
                            <a href="{{ route('users.edit', $u) }}" class="flex items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                                <span class="truncate">{{ $u->name }} <x-position-badge :position="$u->position" class="ml-1" /> @unless($u->is_active)<span class="ml-1 text-xs rounded bg-red-100 text-red-700 px-1.5 py-0.5">Inactive</span>@endunless</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ $u->roleLabel() }}</span>
                            </a>
                        @endforeach
                        <a href="{{ route('users.index', ['department' => $d->id]) }}" class="block px-3 py-2 text-xs text-brand-700 hover:bg-gray-50">Show in Users list</a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
