<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" inputmode="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember" checked>
            <span class="ms-2 text-sm text-gray-600">Keep me logged in on this device</span>
        </label>

        <button type="submit" class="w-full rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold py-3">Log in</button>

        @if (Route::has('password.request'))
            <p class="text-center">
                <a class="text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('password.request') }}">Forgot your password?</a>
            </p>
        @endif
    </form>
</x-guest-layout>
