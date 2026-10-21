@php $editing = $onboarding->exists; @endphp
<x-app-layout :title="$editing ? 'Edit '.$onboarding->candidate_name : 'New hire'">
    <div class="max-w-xl mx-auto">
        <h1 class="text-xl font-bold">{{ $editing ? 'Edit '.$onboarding->candidate_name : 'Start onboarding a new hire' }}</h1>
        @unless ($editing)
            <p class="text-sm text-gray-500 mb-3">After saving you get a private link to share with the candidate. They fill in their details and upload documents there; you then verify them.</p>
        @endunless

        <form method="POST" action="{{ $editing ? route('onboarding.update', $onboarding) : route('onboarding.store') }}"
              class="bg-white rounded-xl border border-gray-200 p-4 space-y-4 mt-3">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field label="Candidate's full name" name="candidate_name" required>
                <input id="candidate_name" name="candidate_name" class="input" required maxlength="120" value="{{ old('candidate_name', $onboarding->candidate_name) }}">
            </x-field>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Mobile" name="candidate_phone" required>
                    <input id="candidate_phone" name="candidate_phone" type="tel" inputmode="numeric" maxlength="10" class="input" required value="{{ old('candidate_phone', $onboarding->candidate_phone) }}">
                </x-field>
                <x-field label="Personal email" name="candidate_email">
                    <input id="candidate_email" name="candidate_email" type="email" class="input" value="{{ old('candidate_email', $onboarding->candidate_email) }}">
                </x-field>
            </div>

            <div class="grid sm:grid-cols-2 gap-4"
                 x-data="{
                     positions: @js($positions),
                     department: @js((string) old('department_id', $onboarding->department_id)),
                     position: @js((string) old('position_id', $onboarding->position_id)),
                     get options() { return this.positions[this.department] ?? []; },
                 }"
                 x-init="$watch('department', () => { if (! options.some(p => String(p.id) === position)) position = '' })">
                <x-field label="Department" name="department_id" required>
                    <select id="department_id" name="department_id" class="input" x-model="department" required>
                        <option value="">Choose…</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id', $onboarding->department_id) == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Position / designation" name="position_id">
                    <select id="position_id" name="position_id" class="input" x-model="position" :disabled="! department">
                        <option value="" x-text="department ? 'Not decided' : 'Choose a department first'">Not decided</option>
                        <template x-for="p in options" :key="p.id">
                            <option :value="String(p.id)" x-text="p.label" :selected="String(p.id) === position"></option>
                        </template>
                    </select>
                </x-field>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Reports to (manager)" name="manager_id" hint="Needed to create their login after approval.">
                    <select id="manager_id" name="manager_id" class="input">
                        <option value="">Not decided</option>
                        @foreach ($managers as $m)
                            <option value="{{ $m->id }}" @selected(old('manager_id', $onboarding->manager_id) == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Work location" name="work_location">
                    <input id="work_location" name="work_location" class="input" maxlength="120" value="{{ old('work_location', $onboarding->work_location) }}" placeholder="e.g. Nashik plant">
                </x-field>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Joining date" name="joining_date">
                    <input id="joining_date" name="joining_date" type="date" class="input" value="{{ old('joining_date', $onboarding->joining_date?->toDateString()) }}">
                </x-field>
                <x-field label="Verification due by" name="target_date">
                    <input id="target_date" name="target_date" type="date" class="input" value="{{ old('target_date', $onboarding->target_date?->toDateString()) }}">
                </x-field>
            </div>

            <x-field label="Case owner (HR)" name="owner_id">
                <select id="owner_id" name="owner_id" class="input">
                    <option value="">Unassigned</option>
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}" @selected(old('owner_id', $onboarding->owner_id) == $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <div class="flex gap-2 pt-2">
                <a href="{{ $editing ? route('onboarding.show', $onboarding) : route('onboarding.index') }}" class="btn-secondary flex-1">Cancel</a>
                <button class="btn-primary flex-[2]">{{ $editing ? 'Save' : 'Create & get candidate link' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
