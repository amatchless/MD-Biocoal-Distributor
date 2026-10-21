@php
    $me = auth()->user();
    $nextType = \App\Models\Attendance::nextTypeFor($me);
    $today = \App\Models\Attendance::where('user_id', $me->id)
        ->betweenLocalDates(now(config('app.display_timezone'))->toDateString(), null)
        ->orderBy('id')->get();
    $openSince = $nextType === \App\Models\Attendance::OUT
        ? \App\Models\Attendance::where('user_id', $me->id)->latest('id')->first()
        : null;
    $firstIn = $today->firstWhere('type', 'in');
    $lastOut = $today->where('type', 'out')->last();
@endphp

<div x-data="attendanceClock({ nextType: @js($nextType), url: @js(route('attendance.store')) })">
<x-card title="Attendance" :subtitle="now(config('app.display_timezone'))->format('l, d M Y')">
    @if (! request()->routeIs('attendance.index'))
        <x-slot name="actions"><a href="{{ route('attendance.index') }}" class="text-sm text-brand-700">History</a></x-slot>
    @endif

    <div class="grid grid-cols-2 gap-3 text-center">
        <div class="rounded-lg bg-gray-50 border border-gray-200 py-2">
            <p class="text-xs text-gray-500">Clocked in</p>
            <p class="text-lg font-semibold">{{ $firstIn ? local_time($firstIn->created_at, 'h:i A') : '—' }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 border border-gray-200 py-2">
            <p class="text-xs text-gray-500">Clocked out</p>
            <p class="text-lg font-semibold">{{ $lastOut ? local_time($lastOut->created_at, 'h:i A') : '—' }}</p>
        </div>
    </div>

    @if ($openSince)
        <p class="mt-3 text-sm text-brand-800 bg-brand-50 border border-brand-200 rounded-lg px-3 py-2">
            You are clocked in since {{ local_time($openSince->created_at, $openSince->localDate() === now(config('app.display_timezone'))->toDateString() ? 'h:i A' : 'd M, h:i A') }}.
        </p>
    @endif

    {{-- Selfie (optional) --}}
    <div class="mt-3">
        <input type="file" accept="image/*" capture="user" class="hidden" x-ref="photoInput" @change="pickedFile($event)">

        <div x-show="cameraOn" x-cloak class="space-y-2">
            <video x-ref="video" playsinline muted class="w-full max-h-80 rounded-lg bg-black object-cover -scale-x-100"></video>
            <div class="flex gap-2">
                <button type="button" class="btn-primary flex-1" @click="snap()">Take photo</button>
                <button type="button" class="btn-secondary" @click="closeCamera()">Cancel</button>
            </div>
        </div>

        <div x-show="photoUrl && !cameraOn" x-cloak class="flex items-center gap-3">
            <img :src="photoUrl" alt="Selfie" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
            <button type="button" class="text-sm text-brand-700 underline" @click="openCamera()">Retake</button>
            <button type="button" class="text-sm text-gray-500 underline" @click="removePhoto()">Remove</button>
        </div>

        <button type="button" x-show="!photoUrl && !cameraOn" class="btn-secondary w-full" @click="openCamera()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 0 1 2-2h2l2-2h6l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.5"/></svg>
            Add selfie (optional)
        </button>
    </div>

    <textarea x-model="notes" rows="2" maxlength="500" class="input mt-3 text-sm" placeholder="Note (optional), e.g. visiting dealers in Nashik"></textarea>

    <button type="button" class="w-full mt-3 py-4 rounded-xl text-lg font-semibold text-white shadow transition disabled:opacity-60"
            :class="nextType === 'in' ? 'bg-brand-700 hover:bg-brand-800' : 'bg-amber-600 hover:bg-amber-700'"
            :disabled="state !== 'idle'" @click="punch()">
        <span x-show="state === 'idle'" x-text="actionLabel">{{ $nextType === 'in' ? 'Clock in' : 'Clock out' }}</span>
        <span x-show="state !== 'idle'" x-cloak>Please wait…</span>
    </button>

    <p class="mt-2 text-sm text-gray-600" x-show="message" x-text="message" x-cloak></p>
    <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error" x-cloak role="alert"></p>
    <p class="mt-2 text-xs text-gray-400">Your GPS location is recorded with each clock in and clock out.</p>
</x-card>
</div>
