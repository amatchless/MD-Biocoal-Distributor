@php
    use App\Models\EmployeeOnboarding;
    use App\Models\OnboardingCheck;
    use App\Models\OnboardingDocument;
    use App\Models\OnboardingReference;
    $o = $onboarding;
    $row = fn ($label, $value) => $value !== null && $value !== '' ? ['label' => $label, 'value' => $value] : null;
    $missing = $o->missingDocuments();
    $checksDone = $o->checksComplete();
    $canEdit = auth()->user()->can('update', $o);
    $link = session('candidate_link');
    $shareText = "Hello {$o->candidate_name}, welcome to MD Biocoals. Please fill in your details and upload your documents for joining here: {$link}";
@endphp

<x-app-layout :title="$o->candidate_name">
    <div class="space-y-4">
        {{-- Header --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 font-mono">{{ $o->ref_no }} · Employee background verification</p>
                    <h1 class="text-xl font-bold truncate">{{ $o->candidate_name }}</h1>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $o->department?->name }}@if ($o->position) · {{ $o->position->label() }}@endif
                        @if ($o->work_location) · {{ $o->work_location }}@endif
                    </p>
                </div>
                <x-onboarding-status :status="$o->status" class="text-sm" />
            </div>
            <dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><dt class="text-gray-500 text-xs">Joining</dt><dd>{{ $o->joining_date?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs">Due by</dt><dd class="{{ $o->isOverdue() ? 'text-red-700 font-semibold' : '' }}">{{ $o->target_date?->format('d M Y') ?? '—' }}{{ $o->isOverdue() ? ' (overdue)' : '' }}</dd></div>
                <div><dt class="text-gray-500 text-xs">Case owner</dt><dd>{{ $o->owner?->name ?? 'Unassigned' }}</dd></div>
                <div><dt class="text-gray-500 text-xs">Reports to</dt><dd>{{ $o->manager?->name ?? '—' }}</dd></div>
            </dl>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="tel:{{ $o->candidate_phone }}" class="btn-secondary py-1.5">Call {{ $o->candidate_phone }}</a>
                @if ($o->candidate_email)<a href="mailto:{{ $o->candidate_email }}" class="btn-secondary py-1.5">Email</a>@endif
                @if ($canEdit)<a href="{{ route('onboarding.edit', $o) }}" class="btn-secondary py-1.5">Edit case details</a>@endif
                @if ($o->submitted_at)<a href="{{ route('onboarding.report', $o) }}" target="_blank" class="btn-primary py-1.5">Verification report</a>@endif
            </div>
            <p class="mt-3 text-xs text-gray-500">Opened by {{ $o->creator?->name }} on {{ local_time($o->created_at) }}@if ($o->submitted_at) · submitted by candidate {{ local_time($o->submitted_at) }}@endif</p>
        </div>

        {{-- Fresh candidate link --}}
        @if ($link)
            <div class="rounded-xl bg-brand-50 border-2 border-brand-300 p-4" x-data="{ copied: false }">
                <p class="font-semibold text-brand-900">Candidate link</p>
                <p class="text-sm text-brand-800">Shown only now. It works for {{ EmployeeOnboarding::LINK_DAYS }} days, until {{ local_time($o->access_expires_at, 'd M Y') }}.</p>
                <input type="text" readonly value="{{ $link }}" class="input font-mono text-xs mt-2" x-ref="link" @focus="$el.select()">
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    <button type="button" class="btn-primary py-1.5" @click="navigator.clipboard.writeText($refs.link.value).then(() => copied = true)" x-text="copied ? 'Copied' : 'Copy link'">Copy link</button>
                    <a class="btn-secondary py-1.5" target="_blank" rel="noopener" href="https://wa.me/91{{ $o->candidate_phone }}?text={{ rawurlencode($shareText) }}">Send on WhatsApp</a>
                    @if ($o->candidate_email)
                        <a class="btn-secondary py-1.5" href="mailto:{{ $o->candidate_email }}?subject={{ rawurlencode('MD Biocoals joining documents') }}&body={{ rawurlencode($shareText) }}">Send by email</a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Waiting on the candidate --}}
        @if (in_array($o->status, EmployeeOnboarding::CANDIDATE_EDITABLE, true))
            <div class="rounded-xl px-4 py-3 text-sm {{ $o->linkExpired() ? 'bg-red-50 border border-red-200 text-red-900' : 'bg-sky-50 border border-sky-200 text-sky-900' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p>
                        @if ($o->status === 'info_pending')<strong>Sent back to the candidate.</strong>@else<strong>Waiting for the candidate</strong> to fill in details and upload documents.@endif
                        {{ $o->linkExpired() ? 'Their link has expired.' : 'Their link works until '.local_time($o->access_expires_at, 'd M Y').'.' }}
                    </p>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('onboarding.link', $o) }}" onsubmit="return confirm('Create a new link? The current link will stop working.')">
                            @csrf
                            <button class="btn-secondary py-1.5">Create new link</button>
                        </form>
                    @endif
                </div>
                @if ($o->info_request)<p class="mt-2 whitespace-pre-line"><span class="font-medium">Asked for:</span> {{ $o->info_request }}</p>@endif
            </div>
        @endif

        @if ($missing->isNotEmpty() && $o->submitted_at)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Required documents missing</p>
                <p class="mt-1">{{ $missing->implode(', ') }}</p>
            </div>
        @endif

        @if ($o->isDecided())
            <div class="rounded-xl px-4 py-3 text-sm {{ $o->status === 'approved' ? 'bg-brand-50 border border-brand-200 text-brand-900' : 'bg-red-50 border border-red-200 text-red-900' }}">
                <p class="font-semibold">{{ $o->statusLabel() }} by {{ $o->decider?->name }} on {{ local_time($o->decided_at) }}</p>
                @if ($o->decision_notes)<p class="mt-1 whitespace-pre-line">{{ $o->decision_notes }}</p>@endif
            </div>
        @endif

        {{-- Login for the approved hire --}}
        @if ($o->user)
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3 text-sm">
                Employee login: <strong>{{ $o->user->email }}</strong>
                @if (auth()->user()->isSuperAdmin()) · <a class="text-brand-700 underline" href="{{ route('users.edit', $o->user) }}">Manage user</a>@endif
            </div>
        @endif
        @can('createAccount', $o)
            <x-card title="Create the employee's login" subtitle="Uses the name, mobile, department, position and manager from this case.">
                <form method="POST" action="{{ route('onboarding.account', $o) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <x-field label="Login email" name="email" required>
                        <input id="email" name="email" type="email" class="input" required value="{{ old('email', $o->candidate_email) }}">
                    </x-field>
                    <x-field label="Temporary password" name="password" required>
                        <input id="password" name="password" type="text" class="input font-mono" required minlength="8" autocomplete="new-password">
                    </x-field>
                    <button class="btn-primary">Create login</button>
                </form>
            </x-card>
        @endcan

        <div class="grid md:grid-cols-2 gap-4">
            {{-- Personal details --}}
            <x-card title="Candidate details" :subtitle="$o->submitted_at ? null : 'Filled in by the candidate through their link.'">
                <dl class="text-sm divide-y divide-gray-100">
                    @forelse (array_filter([
                        $row('Mobile', $o->candidate_phone),
                        $row('Alternate mobile', $o->alternate_phone),
                        $row('Email', $o->candidate_email),
                        $row("Father's / spouse's name", $o->father_name),
                        $row('Date of birth', $o->date_of_birth?->format('d M Y')),
                        $row('Gender', $o->gender),
                        $row('PAN', $o->pan_no),
                        $row('Aadhaar', $o->aadhaar_last4 ? 'XXXX XXXX '.$o->aadhaar_last4 : null),
                        $row('Emergency contact', trim($o->emergency_contact_name.' '.$o->emergency_contact_phone)),
                        $row('Bank', $o->bank_name ? trim($o->bank_name.' · '.$o->maskedBankAccount().' · '.$o->bank_ifsc, ' ·') : null),
                        $row('Declaration accepted', $o->consent_at ? local_time($o->consent_at) : null),
                    ]) as $r)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right">{{ $r['value'] }}</dd></div>
                    @empty
                        <p class="text-gray-500">Nothing filled in yet.</p>
                    @endforelse
                </dl>
            </x-card>

            <x-card title="Address">
                @if ($o->current_address)
                    <p class="text-xs text-gray-500">Current</p>
                    <p class="text-sm whitespace-pre-line">{{ $o->current_address }}, {{ $o->city }}, {{ $o->state }} {{ $o->pincode }}</p>
                    @if ($o->permanent_address)
                        <p class="text-xs text-gray-500 mt-3">Permanent</p>
                        <p class="text-sm whitespace-pre-line">{{ $o->permanent_address }}</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Not filled in yet.</p>
                @endif
            </x-card>

            <x-card title="Education">
                @forelse ($o->education ?? [] as $e)
                    <div class="text-sm py-1.5 border-b border-gray-100 last:border-0">
                        <p class="font-medium">{{ $e['qualification'] ?? '' }} <span class="text-gray-500 font-normal">· {{ $e['year'] ?? '' }}</span></p>
                        <p class="text-gray-600">{{ $e['institution'] ?? '' }}@if (! empty($e['score'])) · {{ $e['score'] }}@endif</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Not filled in yet.</p>
                @endforelse
            </x-card>

            <x-card title="Previous employment">
                @if ($o->is_fresher)
                    <p class="text-sm">Fresher: no previous employment.</p>
                @else
                    @forelse ($o->employment ?? [] as $e)
                        <div class="text-sm py-1.5 border-b border-gray-100 last:border-0">
                            <p class="font-medium">{{ $e['company'] ?? '' }} <span class="text-gray-500 font-normal">· {{ $e['designation'] ?? '' }}</span></p>
                            <p class="text-gray-600">{{ $e['from'] ?? '' }} to {{ ($e['to'] ?? null) ?: 'present' }}</p>
                            @if (! empty($e['contact_name']) || ! empty($e['contact_phone']))
                                <p class="text-gray-600">Reference: {{ $e['contact_name'] ?? '' }} @if (! empty($e['contact_phone']))<a class="text-brand-700 underline" href="tel:{{ $e['contact_phone'] }}">{{ $e['contact_phone'] }}</a>@endif</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Not filled in yet.</p>
                    @endforelse
                @endif
            </x-card>
        </div>

        {{-- Documents --}}
        <x-card title="Documents" :subtitle="$o->documents->count().' file(s)'">
            @if ($o->documents->isEmpty())
                <p class="text-sm text-gray-500">No documents uploaded yet.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($o->documents->sortBy(fn ($d) => array_search($d->category, array_keys(OnboardingDocument::CATEGORIES))) as $doc)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $doc->label() }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $doc->original_name }} · {{ number_format($doc->size / 1024) }} KB · {{ $doc->uploadedByCandidate() ? 'candidate' : $doc->uploader?->name }} · {{ local_time($doc->created_at, 'd M, h:i A') }}</p>
                            </div>
                            <a href="{{ route('onboarding.documents.show', [$o, $doc]) }}" target="_blank" class="btn-secondary py-1 shrink-0">View</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canEdit)
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer text-brand-700 font-medium">Add documents received by HR</summary>
                    <form method="POST" action="{{ route('onboarding.documents.store', $o) }}" enctype="multipart/form-data" class="mt-2 grid sm:grid-cols-3 gap-2 items-end">
                        @csrf
                        <select name="category" class="input" required>
                            @foreach (OnboardingDocument::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                        <input type="file" name="files[]" multiple required accept="image/*,application/pdf" class="text-sm">
                        <button class="btn-primary">Upload</button>
                    </form>
                    @error('files')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('files.*')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </details>
            @endif
        </x-card>

        {{-- Checks --}}
        <x-card title="Verification checks" subtitle="Each check's outcome is recorded with your name and the time.">
            @unless ($o->isVerifying() || $o->isDecided())
                <p class="text-sm text-gray-500 mb-2">You can record checks once the candidate submits.</p>
            @endunless
            <ul class="divide-y divide-gray-100">
                @foreach ($o->checks->sortBy(fn ($c) => array_search($c->check_key, array_keys(OnboardingCheck::CHECKS))) as $check)
                    <li class="py-3" x-data="{ open: {{ $errors->any() && old('check_id') == $check->id ? 'true' : 'false' }}, status: '{{ old('check_id') == $check->id ? old('status') : $check->status }}' }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm">{{ $check->label() }}</p>
                                <p class="text-xs text-gray-500">{{ $check->hint() }}</p>
                                @if ($check->remarks)<p class="text-sm mt-1 whitespace-pre-line">{{ $check->remarks }}</p>@endif
                                @if ($check->checked_at)
                                    <p class="text-xs text-gray-400 mt-1">{{ $check->method ? $check->method.' · ' : '' }}{{ $check->checker?->name ?? 'System' }} · {{ local_time($check->checked_at) }}</p>
                                @endif
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $check->badgeClasses() }}">{{ $check->statusLabel() }}</span>
                                @if ($canEdit && $o->isVerifying())
                                    <button type="button" class="text-xs text-brand-700 underline" @click="open = ! open" x-text="open ? 'Close' : 'Update'">Update</button>
                                @endif
                            </div>
                        </div>
                        @if ($canEdit && $o->isVerifying())
                            <form x-show="open" x-cloak method="POST" action="{{ route('onboarding.checks.update', [$o, $check]) }}" class="mt-2 grid sm:grid-cols-3 gap-2 bg-gray-50 rounded-lg p-3">
                                @csrf
                                <input type="hidden" name="check_id" value="{{ $check->id }}">
                                <select name="status" class="input" x-model="status">
                                    @foreach (OnboardingCheck::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                </select>
                                <select name="method" class="input">
                                    <option value="">Method…</option>
                                    @foreach (OnboardingCheck::METHODS as $m)<option @selected(($check->method ?? '') === $m)>{{ $m }}</option>@endforeach
                                </select>
                                <button class="btn-primary">Save</button>
                                <textarea name="remarks" rows="2" maxlength="2000" class="input sm:col-span-3" :required="@js(OnboardingCheck::NEEDS_REMARKS).includes(status)"
                                          :placeholder="@js(OnboardingCheck::NEEDS_REMARKS).includes(status) ? 'Remarks (required): what was found, or why' : 'Remarks (optional)'">{{ old('check_id') == $check->id ? old('remarks') : $check->remarks }}</textarea>
                                @if (old('check_id') == $check->id)@error('remarks')<p class="text-sm text-red-600 sm:col-span-3">{{ $message }}</p>@enderror @endif
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>

        {{-- Reference contact log --}}
        <x-card title="Reference contact log" subtitle="Calls to previous employers, institutions and personal references.">
            @forelse ($o->references as $ref)
                <div class="text-sm py-2 border-b border-gray-100 last:border-0">
                    <p><span class="font-medium">{{ $ref->contact_name }}</span>@if ($ref->organisation), {{ $ref->organisation }}@endif <span class="text-gray-500">· {{ $ref->kindLabel() }}</span></p>
                    <p class="{{ in_array($ref->outcome, ['discrepancy', 'refused']) ? 'text-red-700' : 'text-gray-700' }}">{{ $ref->outcomeLabel() }}@if ($ref->notes): {{ $ref->notes }}@endif</p>
                    <p class="text-xs text-gray-400">{{ $ref->phone }} · {{ $ref->logger?->name }} · {{ local_time($ref->created_at) }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No contacts logged yet.</p>
            @endforelse
            @if ($canEdit)
                <details class="mt-3 text-sm" @if ($errors->has('contact_name') || $errors->has('outcome')) open @endif>
                    <summary class="cursor-pointer text-brand-700 font-medium">Log a contact</summary>
                    <form method="POST" action="{{ route('onboarding.references.store', $o) }}" class="mt-2 grid sm:grid-cols-2 gap-2">
                        @csrf
                        <select name="kind" class="input">@foreach (OnboardingReference::KINDS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                        <select name="outcome" class="input">@foreach (OnboardingReference::OUTCOMES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                        <input name="contact_name" class="input" placeholder="Person spoken to" required maxlength="120">
                        <input name="organisation" class="input" placeholder="Company / institution" maxlength="150">
                        <input name="phone" class="input" placeholder="Phone" maxlength="20">
                        <input name="notes" class="input" placeholder="What they confirmed" maxlength="2000">
                        @error('contact_name')<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror
                        <button class="btn-primary sm:col-span-2 sm:w-auto sm:justify-self-start">Save contact</button>
                    </form>
                </details>
            @endif
        </x-card>

        {{-- Risk assessment --}}
        @if ($o->isVerifying() || $o->risk_level)
            <x-card title="Risk assessment" subtitle="Consolidate the findings for the approver.">
                @if ($o->risk_level)
                    <p class="text-sm"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ ['low' => 'bg-brand-100 text-brand-800', 'medium' => 'bg-amber-100 text-amber-800', 'high' => 'bg-red-100 text-red-800'][$o->risk_level] }}">{{ $o->riskLabel() }} risk</span>
                        <span class="text-xs text-gray-400">· {{ $o->assessor?->name }} · {{ local_time($o->assessed_at) }}</span></p>
                    <p class="text-sm mt-2 whitespace-pre-line">{{ $o->risk_notes }}</p>
                @endif
                @if ($canEdit && $o->isVerifying())
                    @if (! $checksDone)
                        <p class="text-sm text-amber-800 mt-2">Record an outcome for every check before the assessment.</p>
                    @else
                        <form method="POST" action="{{ route('onboarding.assess', $o) }}" class="mt-3 space-y-2" x-data="{ risk: '{{ old('risk_level', $o->risk_level ?? 'low') }}' }">
                            @csrf
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (EmployeeOnboarding::RISK_LEVELS as $k => $label)
                                    <label class="cursor-pointer rounded-lg border-2 px-2 py-2 text-center text-sm font-medium" :class="risk === '{{ $k }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-gray-200 text-gray-600'">
                                        <input type="radio" name="risk_level" value="{{ $k }}" x-model="risk" class="sr-only">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <textarea name="risk_notes" rows="3" class="input" required maxlength="4000" placeholder="Findings, discrepancies and the reason for this rating">{{ old('risk_notes', $o->risk_notes) }}</textarea>
                            @error('risk_level')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('risk_notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button class="btn-primary">{{ $o->risk_level ? 'Update assessment' : 'Save & send for decision' }}</button>
                        </form>
                    @endif
                @endif
            </x-card>
        @endif

        {{-- Decision --}}
        @if ($o->status === 'risk_review')
            @can('decide', $o)
                <x-card title="Final decision" subtitle="Approve or reject the candidate. Recorded with your name and the time.">
                    <form method="POST" action="{{ route('onboarding.decide', $o) }}" class="space-y-3" x-data="{ decision: '{{ old('decision', 'approved') }}' }">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $k => $label)
                                <label class="cursor-pointer rounded-lg border-2 px-2 py-2 text-center text-sm font-medium" :class="decision === '{{ $k }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-gray-200 text-gray-600'">
                                    <input type="radio" name="decision" value="{{ $k }}" x-model="decision" class="sr-only">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <textarea name="decision_notes" rows="3" class="input" maxlength="2000" :required="decision === 'rejected' || @js($o->risk_level === 'high')"
                                  placeholder="{{ $o->risk_level === 'high' ? 'Reason (required: the assessment is high risk)' : 'Reason (required to reject)' }}">{{ old('decision_notes') }}</textarea>
                        @error('decision_notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <button class="btn-primary">Save decision</button>
                    </form>
                </x-card>
            @else
                <div class="rounded-xl bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-900">Waiting for an HR manager or Super Admin to approve or reject.</div>
            @endcan
        @endif

        {{-- Send back --}}
        @if ($canEdit && $o->isVerifying())
            <x-card title="Send back to the candidate" subtitle="Ask for a missing or unclear document. They get a new link to upload it.">
                <form method="POST" action="{{ route('onboarding.request-info', $o) }}" class="space-y-2">
                    @csrf
                    <textarea name="info_request" rows="2" class="input" required maxlength="2000" placeholder="e.g. The PAN card photo is blurred. Please upload a clear copy.">{{ old('info_request') }}</textarea>
                    @error('info_request')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="btn-secondary">Send back</button>
                </form>
            </x-card>
        @endif

        {{-- Audit trail --}}
        <x-card title="Activity">
            <ol class="space-y-2 text-sm">
                @foreach ($o->activities as $a)
                    <li class="flex gap-3">
                        <span class="text-xs text-gray-400 w-32 shrink-0">{{ local_time($a->created_at, 'd M, h:i A') }}</span>
                        <span>
                            <span class="font-medium">{{ $a->label() }}</span> <span class="text-gray-500">· {{ $a->actorName() }}</span>
                            @if ($a->remarks)<span class="block text-gray-600 whitespace-pre-line">{{ $a->remarks }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </x-card>
    </div>
</x-app-layout>
