@php
    use App\Models\DealerDocument;
    $row = fn ($label, $value) => $value !== null && $value !== '' ? ['label' => $label, 'value' => $value] : null;
    $photos = $dealer->documents->filter(fn ($d) => in_array($d->category, DealerDocument::PHOTO_CATEGORIES));
    $signature = $dealer->documents->where('category', 'signature')->last();
    $docs = $dealer->documents->reject(fn ($d) => in_array($d->category, DealerDocument::PHOTO_CATEGORIES) || $d->category === 'signature');
    $required = $dealer->partner_type === 'distributor' ? DealerDocument::REQUIRED_FOR_DISTRIBUTOR : DealerDocument::REQUIRED_FOR_DEALER;
    $have = $dealer->documents->countBy('category');
    $missing = collect($required)
        ->reject(fn ($n, $cat) => ($have[$cat] ?? 0) >= $n)
        ->reject(fn ($n, $cat) => ($cat === 'pesticide_licence' && ! $dealer->has_pesticide_licence) || ($cat === 'fertilizer_licence' && ! $dealer->has_fertilizer_licence))
        ->map(fn ($n, $cat) => DealerDocument::CATEGORIES[$cat].($n > 1 ? " ({$n})" : ''));
@endphp

<x-app-layout :title="$dealer->business_name">
    <div class="space-y-4">
        {{-- Header --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 font-mono">{{ $dealer->ref_no }} · {{ $dealer->typeLabel() }} · {{ $dealer->applicationTypeLabel() }} · {{ $dealer->financial_year }}</p>
                    <h1 class="text-xl font-bold truncate">{{ $dealer->business_name }}</h1>
                    <p class="text-sm text-gray-600 mt-1">{{ $dealer->city }}, {{ $dealer->district }}, {{ $dealer->state }}</p>
                </div>
                <x-status-badge :status="$dealer->status" class="text-sm" />
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="tel:{{ $dealer->contact_phone }}" class="btn-secondary py-1.5">Call {{ $dealer->contact_name }}</a>
                <a href="{{ $dealer->mapUrl() }}" target="_blank" rel="noopener" class="btn-secondary py-1.5">Directions</a>
                @can('update', $dealer)
                    <a href="{{ route('dealers.edit', $dealer) }}" class="btn-primary py-1.5">Edit</a>
                @endcan
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Onboarded by <strong>{{ $dealer->creator->name }}</strong>@if ($dealer->creator->position), {{ $dealer->creator->position->label() }}@endif
                @if ($dealer->creator->manager) (manager: {{ $dealer->creator->manager->name }}) @endif
                on {{ local_time($dealer->created_at) }}
            </p>
        </div>

        @if ($dealer->review_remarks)
            <div class="rounded-xl px-4 py-3 text-sm {{ $dealer->status === 'rejected' ? 'bg-red-50 text-red-900 border border-red-200' : ($dealer->status === 'needs_info' ? 'bg-orange-50 text-orange-900 border border-orange-200' : 'bg-gray-50 border border-gray-200') }}">
                <p class="font-semibold">Reviewer remarks @if($dealer->reviewer)· {{ $dealer->reviewer->name }}@endif</p>
                <p class="mt-1 whitespace-pre-line">{{ $dealer->review_remarks }}</p>
            </div>
        @endif

        @if ($duplicates->isNotEmpty())
            <div class="rounded-xl bg-amber-50 border border-amber-300 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Possible duplicate</p>
                @foreach ($duplicates as $d)
                    <p>{{ $d->business_name }} ({{ $d->ref_no }}) has the same GSTIN/PAN, onboarded by {{ $d->creator?->name }}.</p>
                @endforeach
            </div>
        @endif

        @if ($missing->isNotEmpty())
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Required documents not uploaded yet</p>
                <p class="mt-1">{{ $missing->implode(', ') }}</p>
            </div>
        @endif

        @foreach ($dealer->expiringLicences() as $lic)
            <div class="rounded-xl px-4 py-3 text-sm {{ $lic['expired'] ? 'bg-red-50 text-red-900 border border-red-200' : 'bg-amber-50 text-amber-900 border border-amber-200' }}">
                {{ $lic['kind'] }} licence {{ $lic['expired'] ? 'expired on' : 'expires on' }} {{ $lic['expiry']->format('d M Y') }}.
            </div>
        @endforeach

        {{-- Review --}}
        @can('review', $dealer)
            <x-card title="Review" subtitle="Your decision is recorded with your name and the time.">
                <form method="POST" action="{{ route('dealers.review', $dealer) }}" class="space-y-3" x-data="{ status: '{{ old('status', $dealer->status === 'submitted' ? 'under_review' : $dealer->status) }}' }">
                    @csrf
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach (['under_review' => 'Under review', 'needs_info' => 'Needs info', 'approved' => 'Approve', 'rejected' => 'Reject'] as $key => $label)
                            <label class="cursor-pointer rounded-lg border-2 px-2 py-2 text-center text-sm font-medium"
                                   :class="status === '{{ $key }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-gray-200 text-gray-600'">
                                <input type="radio" name="status" value="{{ $key }}" x-model="status" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <textarea name="remarks" rows="3" class="input" maxlength="2000" :required="['needs_info','rejected'].includes(status)"
                              :placeholder="status === 'needs_info' ? 'What should the employee fix or collect?' : 'Remarks (required for Needs info and Reject)'">{{ old('remarks') }}</textarea>
                    @error('remarks')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="btn-primary w-full sm:w-auto">Save decision</button>
                </form>
            </x-card>
        @endcan

        <div class="grid md:grid-cols-2 gap-4">
            {{-- Location --}}
            <x-card title="Location captured at premises">
                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div><span class="text-gray-500">Lat, Lng</span><div class="font-mono text-xs">{{ number_format($dealer->latitude, 6) }}, {{ number_format($dealer->longitude, 6) }}</div></div>
                    <div><span class="text-gray-500">Accuracy</span>
                        <div class="font-semibold {{ $dealer->geo_accuracy > 100 ? 'text-red-700' : ($dealer->geo_accuracy > 30 ? 'text-amber-700' : 'text-brand-700') }}">
                            {{ $dealer->geo_accuracy !== null ? '±'.round($dealer->geo_accuracy).' m' : '—' }}
                        </div>
                    </div>
                    <div class="col-span-2"><span class="text-gray-500">Captured</span> {{ local_time($dealer->geo_captured_at) }}</div>
                </div>
                @php $d = 0.004; @endphp
                <iframe class="w-full h-56 rounded-lg border border-gray-200" loading="lazy" title="Premises location"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $dealer->longitude - $d }},{{ $dealer->latitude - $d }},{{ $dealer->longitude + $d }},{{ $dealer->latitude + $d }}&layer=mapnik&marker={{ $dealer->latitude }},{{ $dealer->longitude }}"></iframe>
                <p class="mt-2 text-sm text-gray-700">{{ $dealer->address_line1 }}@if($dealer->address_line2), {{ $dealer->address_line2 }}@endif, {{ $dealer->city }}@if($dealer->tehsil), Tehsil {{ $dealer->tehsil }}@endif, Distt. {{ $dealer->district }}, {{ $dealer->state }} {{ $dealer->pincode }}</p>
            </x-card>

            {{-- Registration form cover --}}
            <x-card title="Registration">
                <dl class="text-sm divide-y divide-gray-100">
                    @foreach (array_filter([
                        $row('Form', $dealer->typeLabel().' · '.$dealer->applicationTypeLabel()),
                        $row('Year', $dealer->financial_year),
                        $row('Constitution', $dealer->entity_type),
                        $row('Contact person', $dealer->contact_name),
                        $row('Mobile', $dealer->contact_phone),
                        $row('Official email', $dealer->email),
                        $row('State head', $dealer->state_head),
                    ]) as $r)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right">{{ $r['value'] }}</dd></div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Registrations --}}
            <x-card title="GST, PAN & licences">
                <dl class="text-sm divide-y divide-gray-100">
                    @foreach (array_filter([
                        $row('GSTIN', $dealer->gst_registered ? $dealer->gst_no : 'Not GST registered'),
                        $row('PAN', $dealer->pan_no),
                        $row('Aadhaar', $dealer->aadhaar_last4 ? 'XXXX XXXX '.$dealer->aadhaar_last4 : null),
                        $row('Pesticide licence', $dealer->has_pesticide_licence ? $dealer->pesticide_licence_no.' (till '.$dealer->pesticide_licence_expiry?->format('d M Y').')' : 'None'),
                        $row('Fertilizer licence', $dealer->has_fertilizer_licence ? $dealer->fertilizer_licence_no.' (till '.$dealer->fertilizer_licence_expiry?->format('d M Y').')' : 'None'),
                    ]) as $r)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right font-mono text-xs sm:text-sm">{{ $r['value'] }}</dd></div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Security deposit & cheques --}}
            <x-card title="Security deposit & cheques">
                <dl class="text-sm divide-y divide-gray-100">
                    @forelse (array_filter([
                        $row('Deposit', $dealer->security_deposit_amount !== null ? '₹'.number_format($dealer->security_deposit_amount) : null),
                        $row('Paid by', trim($dealer->security_deposit_mode.' '.$dealer->security_deposit_ref)),
                        $row('Date paid', $dealer->security_deposit_date?->format('d M Y')),
                        $row('Bank', trim($dealer->bank_name.($dealer->bank_branch ? ', '.$dealer->bank_branch : ''))),
                        $row('Account', $dealer->bank_account_no ? trim($dealer->maskedBankAccount().' · '.$dealer->bank_ifsc, ' ·') : null),
                        $row('Account holder', $dealer->bank_account_name),
                        $row('Cheque nos.', implode(', ', array_filter([$dealer->cheque_1_no, $dealer->cheque_2_no]))),
                    ]) as $r)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right font-mono text-xs sm:text-sm">{{ $r['value'] }}</dd></div>
                    @empty
                        <p class="text-gray-500">Not recorded.</p>
                    @endforelse
                </dl>
            </x-card>

            {{-- TM & above block --}}
            <x-card title="Company officials">
                <dl class="text-sm divide-y divide-gray-100">
                    @foreach (array_filter([
                        $row('Emp. code', $dealer->employee_code),
                        $row('Manager', $dealer->manager_name),
                        $row('Appointing manager', trim($dealer->appointing_manager_name.($dealer->appointing_manager_code ? ' ('.$dealer->appointing_manager_code.')' : ''))),
                        $row('Designation', $dealer->appointing_manager_designation),
                        $row('Mobile', $dealer->appointing_manager_mobile),
                        $row('Area / state', trim($dealer->appointing_manager_area.($dealer->appointing_manager_state ? ', '.$dealer->appointing_manager_state : ''), ', ')),
                        $row('Reporting head', trim($dealer->reporting_head_name.($dealer->reporting_head_designation ? ' ('.$dealer->reporting_head_designation.')' : ''))),
                    ]) as $r)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right">{{ $r['value'] }}</dd></div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Policy / agreement acceptance and signature --}}
            <x-card :title="$dealer->partner_type === 'distributor' ? 'Marketing policy' : 'Dealer agreement'" class="md:col-span-2">
                <div class="grid sm:grid-cols-2 gap-4">
                    <dl class="text-sm divide-y divide-gray-100">
                        @foreach (array_filter([
                            $row('Accepted', $dealer->terms_version ? $dealer->terms_version.' · '.local_time($dealer->terms_accepted_at) : null),
                            $row('Cash discount', $dealer->cashDiscountLabel()),
                            $row('Agreement date', $dealer->agreement_date?->format('d M Y')),
                            $row('Signed by', trim($dealer->signatory_name.($dealer->signatory_designation ? ' ('.$dealer->signatory_designation.')' : ''))),
                            $row('Witness 1', trim($dealer->witness1_name.($dealer->witness1_address ? ', '.$dealer->witness1_address : ''), ', ')),
                            $row('Witness 2', trim($dealer->witness2_name.($dealer->witness2_address ? ', '.$dealer->witness2_address : ''), ', ')),
                        ]) as $r)
                            <div class="flex justify-between gap-4 py-1.5"><dt class="text-gray-500 shrink-0">{{ $r['label'] }}</dt><dd class="text-right">{{ $r['value'] }}</dd></div>
                        @endforeach
                    </dl>
                    <div>
                        @if ($signature)
                            <img src="{{ route('dealers.documents.show', [$dealer, $signature]) }}" alt="Signature" class="w-full h-32 object-contain rounded-lg border border-gray-200 bg-white">
                            <p class="mt-1 text-xs text-gray-500">Signed {{ local_time($signature->created_at) }}</p>
                        @else
                            <p class="text-sm text-gray-500">No signature on file.</p>
                        @endif
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Photos --}}
        <x-card title="Site photos" subtitle="Stamped with GPS at capture">
            @if ($photos->isEmpty())
                <p class="text-sm text-gray-500">No photos.</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($photos as $doc)
                        <a href="{{ route('dealers.documents.show', [$dealer, $doc]) }}" target="_blank" class="block rounded-lg overflow-hidden border border-gray-200">
                            <img src="{{ route('dealers.documents.show', [$dealer, $doc]) }}" alt="{{ $doc->label() }}" class="w-full h-32 object-cover" loading="lazy">
                            <div class="p-2 text-xs">
                                <p class="font-medium">{{ $doc->label() }}</p>
                                @if ($doc->latitude)
                                    @php $dist = \App\Support\Geo::distanceMeters($doc->latitude, $doc->longitude, $dealer->latitude, $dealer->longitude); @endphp
                                    <p class="{{ $dist > 200 ? 'text-red-600' : 'text-gray-500' }}">{{ round($dist) }} m from premises pin</p>
                                @else
                                    <p class="text-gray-400">No GPS</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Documents --}}
        <x-card title="Documents">
            @if ($docs->isEmpty())
                <p class="text-sm text-gray-500">No documents uploaded.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($docs as $doc)
                        <li class="py-2 flex items-center justify-between gap-3">
                            <span>{{ $doc->label() }} <span class="text-xs text-gray-400">{{ $doc->isImage() ? 'Photo' : 'PDF' }} · {{ number_format($doc->size / 1024) }} KB</span></span>
                            <a href="{{ route('dealers.documents.show', [$dealer, $doc]) }}" target="_blank" class="text-brand-700 font-medium">View</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('addDocuments', $dealer)
                <form method="POST" action="{{ route('dealers.documents.store', $dealer) }}" enctype="multipart/form-data" class="mt-4 border-t pt-4 flex flex-col sm:flex-row gap-2">
                    @csrf
                    <select name="documents[0][category]" class="input sm:w-64">
                        @foreach (DealerDocument::CATEGORIES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="file" name="documents[0][file]" accept="image/*,application/pdf" required class="text-sm">
                    <button class="btn-secondary">Upload</button>
                </form>
                @error('documents.0.file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @endcan
        </x-card>

        @if ($dealer->visit_notes)
            <x-card title="Visit notes">
                <p class="text-sm whitespace-pre-line">{{ $dealer->visit_notes }}</p>
            </x-card>
        @endif

        {{-- History --}}
        <x-card title="History">
            <ol class="text-sm space-y-3">
                @foreach ($dealer->activities as $a)
                    <li class="flex gap-3">
                        <span class="mt-1.5 w-2 h-2 rounded-full bg-brand-500 shrink-0"></span>
                        <div>
                            <p><strong>{{ $a->label() }}</strong> by {{ $a->user?->name ?? 'system' }}</p>
                            <p class="text-xs text-gray-500">{{ local_time($a->created_at) }}</p>
                            @if ($a->remarks)<p class="text-gray-700 mt-0.5 whitespace-pre-line">{{ $a->remarks }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
            <p class="mt-4 text-xs text-gray-400">Consent recorded {{ local_time($dealer->consent_at) }} · Submitted from {{ $dealer->ip_address }}</p>
        </x-card>
    </div>
</x-app-layout>
