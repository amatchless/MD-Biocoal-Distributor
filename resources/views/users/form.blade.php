@php $editing = $user->exists; @endphp
<x-app-layout :title="$editing ? 'Edit '.$user->name : 'Add user'">
    <div class="max-w-xl mx-auto">
        <h1 class="text-xl font-bold mb-3">{{ $editing ? 'Edit '.$user->name : 'Add user' }}</h1>

        <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
              class="bg-white rounded-xl border border-gray-200 p-4 space-y-4"
              x-data="{ role: '{{ old('role', $editing ? $user->roleName() : \App\Models\User::EMPLOYEE) }}' }">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field label="Full name" name="name" required>
                <input id="name" name="name" class="input" required value="{{ old('name', $user->name) }}">
            </x-field>
            <x-field label="Email (used to log in)" name="email" required>
                <input id="email" name="email" type="email" class="input" required value="{{ old('email', $user->email) }}" autocomplete="off">
            </x-field>
            <x-field label="Mobile" name="phone">
                <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="10" class="input" value="{{ old('phone', $user->phone) }}">
            </x-field>

            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">Role <span class="text-red-600">*</span></legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (\App\Models\User::ROLE_LABELS as $k => $label)
                        <label class="cursor-pointer rounded-lg border-2 px-2 py-2 text-center text-sm font-medium"
                               :class="role === '{{ $k }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-gray-200 text-gray-600'">
                            <input type="radio" name="role" value="{{ $k }}" x-model="role" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>

            <div x-show="role === 'employee'">
                <x-field label="Reports to (manager)" name="manager_id" required>
                    <select id="manager_id" name="manager_id" class="input">
                        <option value="">Choose a manager…</option>
                        @foreach ($managers as $m)
                            <option value="{{ $m->id }}" @selected(old('manager_id', $user->manager_id) == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($managers->isEmpty())<p class="mt-1 text-sm text-amber-700">Add a manager first, then assign employees to them.</p>@endif
            </div>

            <x-field label="Department" name="department_id">
                <select id="department_id" name="department_id" class="input">
                    <option value="">No department</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Territory / region" name="territory">
                <input id="territory" name="territory" class="input" value="{{ old('territory', $user->territory) }}" placeholder="e.g. Nadia district">
            </x-field>

            <x-field :label="$editing ? 'New password' : 'Password'" name="password" :required="! $editing" :hint="$editing ? 'Leave blank to keep the current password.' : 'At least 8 characters. Share it with the user privately.'">
                <input id="password" name="password" type="text" class="input font-mono" autocomplete="new-password" @unless($editing) required minlength="8" @endunless>
            </x-field>

            <label class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-600 focus:ring-brand-500" @checked(old('is_active', $user->is_active ?? true))>
                <span class="text-sm">Active (can log in)</span>
            </label>

            <div class="flex gap-2 pt-2">
                <a href="{{ route('users.index') }}" class="btn-secondary flex-1">Cancel</a>
                <button class="btn-primary flex-[2]">{{ $editing ? 'Save' : 'Create user' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
