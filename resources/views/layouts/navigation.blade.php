@php
    $user = Auth::user();
    $links = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Home', 'icon' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'],
        ['route' => 'dealers.index', 'match' => 'dealers.index', 'label' => $user->isEmployee() ? 'My onboardings' : 'Dealers', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
    ];
    if ($user->isSuperAdmin()) {
        $links[] = ['route' => 'users.index', 'match' => 'users.*', 'label' => 'Users', 'icon' => 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 9v-1a4 4 0 0 0-3-3.87M16 2.13a4 4 0 0 1 0 7.75'];
    }
@endphp

{{-- Top bar --}}
<nav class="bg-brand-800 text-white sticky top-0 z-30 shadow" style="padding-top: env(safe-area-inset-top)">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo class="w-8 h-8 shrink-0" />
            <span class="font-semibold truncate">MD Biocoals <span class="font-normal text-brand-200 hidden sm:inline">· Onboarding</span></span>
        </a>

        <div class="hidden md:flex items-center gap-1">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($link['match']) ? 'bg-brand-900 text-white' : 'text-brand-100 hover:bg-brand-700' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ route('dealers.create') }}" class="ml-2 px-3 py-2 rounded-md text-sm font-semibold bg-white text-brand-800 hover:bg-brand-50">+ Onboard</a>
        </div>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-white/60 pl-1 pr-2 py-1 hover:bg-brand-700">
                    <span class="w-8 h-8 rounded-full bg-brand-600 grid place-items-center font-semibold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <span class="hidden sm:block text-left leading-tight">
                        <span class="block font-medium">{{ $user->name }}</span>
                        <span class="block text-xs text-brand-200">{{ $user->roleLabel() }}</span>
                    </span>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="px-4 py-2 text-xs text-gray-500 sm:hidden">{{ $user->name }} · {{ $user->roleLabel() }}</div>
                <x-dropdown-link :href="route('profile.edit')">Profile & password</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log out</x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</nav>

{{-- Bottom tab bar on phones --}}
<nav class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-gray-200" style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="grid {{ count($links) === 3 ? 'grid-cols-4' : 'grid-cols-3' }} h-16 text-xs">
        @foreach (array_slice($links, 0, 2) as $link)
            <a href="{{ route($link['route']) }}" class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs($link['match']) ? 'text-brand-700 font-semibold' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $link['icon'] }}"/></svg>
                {{ $link['label'] }}
            </a>
        @endforeach
        <a href="{{ route('dealers.create') }}" class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs('dealers.create') ? 'text-brand-700 font-semibold' : 'text-gray-500' }}">
            <span class="w-10 h-10 -mt-1 rounded-full bg-brand-700 text-white grid place-items-center text-2xl leading-none shadow">+</span>
            Onboard
        </a>
        @if (count($links) === 3)
            <a href="{{ route($links[2]['route']) }}" class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs($links[2]['match']) ? 'text-brand-700 font-semibold' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $links[2]['icon'] }}"/></svg>
                {{ $links[2]['label'] }}
            </a>
        @endif
    </div>
</nav>
