@php
    use App\Models\Dealer;
    use App\Models\DealerDocument;

    $isEdit = $dealer->exists;
    $v = fn (string $key, $default = null) => old($key, $dealer->{$key} ?? $default);
    $date = fn (string $key) => old($key, optional($dealer->{$key})->format('Y-m-d'));
    $bool = fn (string $key, bool $default = false) => (bool) old($key, $dealer->{$key} ?? $default);
    $manager = auth()->user()->manager;

    // Steps follow the paper Distributor's Registration & Renewal Form, Marketing Policy and Dealer Agreement.
    $steps = [
        ['key' => 'application', 'label' => 'Registration'],
        ['key' => 'location', 'label' => 'Address & GPS'],
        ['key' => 'registrations', 'label' => 'GST, PAN & licences'],
        ['key' => 'deposit', 'label' => 'Security deposit & cheques'],
        ['key' => 'officials', 'label' => 'Company officials'],
        ['key' => 'documents', 'label' => 'Photos & documents'],
        ['key' => 'terms', 'label' => 'Policy / agreement & signature'],
        ['key' => 'review', 'label' => 'Confirm'],
    ];

    $config = [
        'steps' => $steps,
        'isEdit' => $isEdit,
        'dealerId' => $dealer->id,
        'draftKey' => 'mdb-draft-new-'.auth()->id(),
        'employeeName' => auth()->user()->name,
        'photoCategories' => DealerDocument::PHOTO_CATEGORIES,
        'categories' => DealerDocument::CATEGORIES,
        'required' => ['distributor' => DealerDocument::REQUIRED_FOR_DISTRIBUTOR, 'dealer' => DealerDocument::REQUIRED_FOR_DEALER],
        'existingDocs' => $isEdit ? $dealer->documents()->toBase()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category') : (object) [],
        'duplicateUrl' => route('dealers.duplicate-check'),
        'doneUrl' => route('dealers.index'),
        'geo' => $isEdit ? [
            'lat' => $dealer->latitude, 'lng' => $dealer->longitude,
            'accuracy' => $dealer->geo_accuracy ?? 0, 'captured_at' => optional($dealer->geo_captured_at)->toIso8601String(),
        ] : null,
        'values' => [
            'partner_type' => $v('partner_type', 'dealer'),
            'gst_registered' => $bool('gst_registered', true) ? '1' : '0',
            'has_pesticide_licence' => $bool('has_pesticide_licence') ? '1' : '0',
            'has_fertilizer_licence' => $bool('has_fertilizer_licence') ? '1' : '0',
        ],
    ];
@endphp

<x-app-layout :title="$isEdit ? 'Edit '.$dealer->business_name : 'Dealer / distributor registration'">
    <div x-data="onboardingForm(@js($config))" class="max-w-2xl mx-auto">
        <div class="mb-3">
            <h1 class="text-xl font-bold">{{ $isEdit ? 'Edit '.$dealer->ref_no : 'Registration & renewal form' }}</h1>
            <p class="text-sm text-gray-500" x-text="`Step ${step + 1} of ${steps.length}: ${steps[step].label}`"></p>
        </div>

        {{-- Progress --}}
        <div class="flex gap-1 mb-4" role="tablist">
            <template x-for="(s, i) in steps" :key="s.key">
                <button type="button" @click="goTo(i)" :aria-label="s.label"
                        class="h-2 flex-1 rounded-full transition"
                        :class="stepHasError(i) ? 'bg-red-500' : (i <= step ? 'bg-brand-600' : 'bg-gray-300')"></button>
            </template>
        </div>

        @if ($isEdit && $dealer->status === 'needs_info' && $dealer->review_remarks)
            <div class="mb-4 rounded-lg bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-900">
                <p class="font-semibold">Your manager asked for more information</p>
                <p class="mt-1 whitespace-pre-line">{{ $dealer->review_remarks }}</p>
            </div>
        @endif

        <div x-show="draftAvailable" x-cloak class="mb-4 rounded-lg bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-900">
            <p>You have an unfinished form saved on this device <span x-text="draftAvailable ? '(' + draftLabel(draftAvailable.saved_at) + ')' : ''"></span>. Photos and the signature are not kept in drafts.</p>
            <div class="mt-2 flex gap-2">
                <button type="button" class="btn-primary py-1.5" @click="restoreDraft()">Continue it</button>
                <button type="button" class="btn-secondary py-1.5" @click="discardDraft()">Start fresh</button>
            </div>
        </div>

        <div x-show="formError" x-cloak class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" x-text="formError" role="alert"></div>

        <form method="POST" action="{{ $isEdit ? route('dealers.update', $dealer) : route('dealers.store') }}"
              enctype="multipart/form-data" novalidate
              @submit.prevent="submit()" @change.debounce.800ms="saveDraft()" @input.debounce.1500ms="saveDraft()">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            {{-- 1. Registration (cover of the paper form) --}}
            <section data-step="0" x-show="step === 0" class="space-y-4 bg-white rounded-xl border border-gray-200 p-4">
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 mb-2">Registering a <span class="text-red-600">*</span></legend>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (Dealer::TYPES as $key => $label)
                            <label class="cursor-pointer rounded-xl border-2 p-3 text-center font-semibold"
                                   :class="partnerType === '{{ $key }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-gray-200 text-gray-600'">
                                <input type="radio" name="partner_type" value="{{ $key }}" class="sr-only" x-model="partnerType" required @checked($v('partner_type', 'dealer') === $key)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid grid-cols-2 gap-4">
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">New or renewal <span class="text-red-600">*</span></legend>
                        <div class="flex gap-4 py-2">
                            @foreach (Dealer::APPLICATION_TYPES as $key => $label)
                                <label class="flex items-center gap-2"><input type="radio" name="application_type" value="{{ $key }}" required class="text-brand-600 focus:ring-brand-500" @checked($v('application_type', 'new') === $key)> {{ $label }}</label>
                            @endforeach
                        </div>
                        <p class="text-sm text-red-600" x-show="err('application_type')" x-text="err('application_type')" x-cloak></p>
                    </fieldset>
                    <x-dealer-field label="Year" name="financial_year" required hint="Financial year">
                        <input id="financial_year" name="financial_year" class="input" required pattern="20[0-9]{2}-[0-9]{2}" maxlength="7" value="{{ $v('financial_year', Dealer::currentFinancialYear()) }}" placeholder="2026-27" title="Financial year, e.g. 2026-27">
                    </x-dealer-field>
                </div>

                <x-dealer-field label="Name of dealer / distributor" name="business_name" required hint="Firm name as on GST and letterhead">
                    <input id="business_name" name="business_name" class="input" required maxlength="200" value="{{ $v('business_name') }}" autocomplete="organization">
                </x-dealer-field>
                <x-dealer-field label="Constitution of the firm" name="entity_type" required>
                    <select id="entity_type" name="entity_type" class="input" required>
                        <option value="">Choose…</option>
                        @foreach (Dealer::ENTITY_TYPES as $t)
                            <option @selected($v('entity_type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </x-dealer-field>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-dealer-field label="Contact person" name="contact_name" required>
                        <input id="contact_name" name="contact_name" class="input" required maxlength="120" value="{{ $v('contact_name') }}" autocomplete="name">
                    </x-dealer-field>
                    <x-dealer-field label="Mobile no." name="contact_phone" required>
                        <input id="contact_phone" name="contact_phone" class="input" type="tel" inputmode="numeric" required pattern="[6-9][0-9]{9}" maxlength="10" value="{{ $v('contact_phone') }}" placeholder="10-digit mobile" title="10-digit mobile number">
                    </x-dealer-field>
                    <x-dealer-field label="Official email ID" name="email" hint="Optional">
                        <input id="email" name="email" class="input" type="email" inputmode="email" maxlength="150" value="{{ $v('email') }}">
                    </x-dealer-field>
                    <x-dealer-field label="State head" name="state_head">
                        <input id="state_head" name="state_head" class="input" maxlength="120" value="{{ $v('state_head') }}">
                    </x-dealer-field>
                </div>
            </section>

            {{-- 2. Address & GPS --}}
            <section data-step="1" x-show="step === 1" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border-2 p-4" :class="geo ? 'border-brand-300' : 'border-amber-300'">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Premises GPS location <span class="text-red-600">*</span></h3>
                            <p class="text-xs text-gray-500 mt-0.5">Capture this while standing at the dealer's shop or office.</p>
                        </div>
                        <svg class="w-6 h-6 shrink-0" :class="geo ? 'text-brand-600' : 'text-amber-500'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </div>

                    <template x-if="!secure">
                        <p class="mt-3 text-sm rounded-lg bg-red-50 text-red-800 p-3">This page is not on HTTPS, so phones will refuse to share location. Open the app through its https:// address.</p>
                    </template>

                    <template x-if="geo">
                        <div class="mt-3 space-y-2">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div><span class="text-gray-500">Latitude</span><div class="font-mono" x-text="geo.lat.toFixed(6)"></div></div>
                                <div><span class="text-gray-500">Longitude</span><div class="font-mono" x-text="geo.lng.toFixed(6)"></div></div>
                                <div><span class="text-gray-500">Accuracy</span><div class="font-semibold" :class="accuracyClass" x-text="geo.accuracy ? '±' + Math.round(geo.accuracy) + ' m' : '—'"></div></div>
                                <div><span class="text-gray-500">Captured</span><div x-text="geo.captured_at ? new Date(geo.captured_at).toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'}) : '—'"></div></div>
                            </div>
                            <p x-show="geo.accuracy > 100" class="text-sm text-red-700">Accuracy is poor. Step outside, wait a few seconds and capture again.</p>
                            <iframe :src="mapEmbed" class="w-full h-48 rounded-lg border border-gray-200" loading="lazy" title="Captured location"></iframe>
                            <a :href="mapLink" target="_blank" rel="noopener" class="text-sm text-brand-700 underline">Open in Google Maps</a>
                        </div>
                    </template>

                    <p class="mt-3 text-sm" :class="geoState === 'error' ? 'text-red-700' : 'text-gray-600'" x-show="geoMessage" x-text="geoMessage"></p>
                    <p class="mt-1 text-sm text-red-600" x-show="err('latitude')" x-text="err('latitude')"></p>

                    <button type="button" class="btn-primary w-full mt-3 py-3" @click="captureLocation()" :disabled="geoState === 'locating'">
                        <span x-show="geoState !== 'locating'" x-text="geo ? 'Capture again' : 'Capture current location'"></span>
                        <span x-show="geoState === 'locating'">Locating…</span>
                    </button>

                    <input type="hidden" name="latitude" :value="geo ? geo.lat : ''">
                    <input type="hidden" name="longitude" :value="geo ? geo.lng : ''">
                    <input type="hidden" name="geo_accuracy" :value="geo ? geo.accuracy : ''">
                    <input type="hidden" name="geo_captured_at" :value="geo ? geo.captured_at : ''">
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <x-dealer-field label="Address" name="address_line1" required>
                        <input id="address_line1" name="address_line1" class="input" required maxlength="200" value="{{ $v('address_line1') }}" placeholder="Shop no., building, street" autocomplete="address-line1">
                    </x-dealer-field>
                    <x-dealer-field label="Address (continued)" name="address_line2">
                        <input id="address_line2" name="address_line2" class="input" maxlength="200" value="{{ $v('address_line2') }}" placeholder="Area, mandi, landmark" autocomplete="address-line2">
                    </x-dealer-field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-dealer-field label="Town / village" name="city" required hint="Place of the dealership">
                            <input id="city" name="city" class="input" required maxlength="80" value="{{ $v('city') }}">
                        </x-dealer-field>
                        <x-dealer-field label="Tehsil / taluk" name="tehsil">
                            <input id="tehsil" name="tehsil" class="input" maxlength="80" value="{{ $v('tehsil') }}">
                        </x-dealer-field>
                        <x-dealer-field label="Distt." name="district" required>
                            <input id="district" name="district" class="input" required maxlength="80" value="{{ $v('district') }}">
                        </x-dealer-field>
                        <x-dealer-field label="Pincode" name="pincode" required>
                            <input id="pincode" name="pincode" class="input" inputmode="numeric" required pattern="[1-9][0-9]{5}" maxlength="6" value="{{ $v('pincode') }}" title="6-digit PIN code">
                        </x-dealer-field>
                        <x-dealer-field label="State" name="state" required class="col-span-2">
                            <select id="state" name="state" class="input" required>
                                <option value="">Choose…</option>
                                @foreach (Dealer::STATES as $s)
                                    <option @selected($v('state') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </x-dealer-field>
                    </div>
                </div>
            </section>

            {{-- 3. Registrations & licences --}}
            <section data-step="2" x-show="step === 2" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">GST, PAN and Aadhaar</h3>
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">GST registered? <span class="text-red-600">*</span></legend>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2"><input type="radio" name="gst_registered" value="1" class="text-brand-600 focus:ring-brand-500" x-model="gstRegistered"> Yes</label>
                            <label class="flex items-center gap-2"><input type="radio" name="gst_registered" value="0" class="text-brand-600 focus:ring-brand-500" x-model="gstRegistered"> No</label>
                        </div>
                    </fieldset>
                    <x-dealer-field label="GSTIN" name="gst_no" x-show="gstRegistered == '1'" required>
                        <input id="gst_no" name="gst_no" class="input uppercase font-mono" maxlength="15" value="{{ $v('gst_no') }}"
                               pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z][Zz][0-9A-Za-z]" title="15-character GSTIN"
                               :required="gstRegistered == '1'" :disabled="!(gstRegistered == '1')"
                               @input="onGstInput($event)" @blur="checkDuplicates()" placeholder="22AAAAA0000A1Z5" autocapitalize="characters">
                    </x-dealer-field>
                    <x-dealer-field label="PAN" name="pan_no" required hint="Of the firm (or the proprietor for a proprietorship)">
                        <input id="pan_no" name="pan_no" class="input uppercase font-mono" maxlength="10" required value="{{ $v('pan_no') }}"
                               pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" title="10-character PAN, e.g. ABCDE1234F"
                               @input="$event.target.value = $event.target.value.toUpperCase()" @blur="checkDuplicates()" placeholder="ABCDE1234F" autocapitalize="characters">
                    </x-dealer-field>

                    <div x-show="duplicates.length" x-cloak class="rounded-lg bg-amber-50 border border-amber-300 p-3 text-sm text-amber-900">
                        <p class="font-semibold">Already registered?</p>
                        <template x-for="d in duplicates" :key="d.ref_no">
                            <p x-text="`${d.business_name} (${d.ref_no}) matches this ${d.matched_on}. Onboarded by ${d.onboarded_by}, status: ${d.status}.`"></p>
                        </template>
                    </div>

                    <x-dealer-field label="Aadhaar: last 4 digits only" name="aadhaar_last4" hint="Do not record the full Aadhaar number">
                        <input id="aadhaar_last4" name="aadhaar_last4" class="input font-mono" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ $v('aadhaar_last4') }}" placeholder="XXXX" title="Last 4 digits">
                    </x-dealer-field>
                </div>

                @foreach (['pesticide' => 'hasPesticide', 'fertilizer' => 'hasFertilizer'] as $kind => $model)
                    <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                        <fieldset>
                            <legend class="font-semibold mb-2">{{ ucfirst($kind) }} licence</legend>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-2"><input type="radio" name="has_{{ $kind }}_licence" value="1" class="text-brand-600 focus:ring-brand-500" x-model="{{ $model }}"> Holds licence</label>
                                <label class="flex items-center gap-2"><input type="radio" name="has_{{ $kind }}_licence" value="0" class="text-brand-600 focus:ring-brand-500" x-model="{{ $model }}"> No licence</label>
                            </div>
                        </fieldset>
                        <div class="grid sm:grid-cols-2 gap-4" x-show="{{ $model }} == '1'">
                            <x-dealer-field label="Licence number" name="{{ $kind }}_licence_no" required>
                                <input id="{{ $kind }}_licence_no" name="{{ $kind }}_licence_no" class="input" maxlength="60" value="{{ $v($kind.'_licence_no') }}"
                                       :required="{{ $model }} == '1'" :disabled="!({{ $model }} == '1')">
                            </x-dealer-field>
                            <x-dealer-field label="Valid until" name="{{ $kind }}_licence_expiry" required>
                                <input id="{{ $kind }}_licence_expiry" name="{{ $kind }}_licence_expiry" class="input" type="date" value="{{ $date($kind.'_licence_expiry') }}"
                                       :required="{{ $model }} == '1'" :disabled="!({{ $model }} == '1')">
                            </x-dealer-field>
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- 4. Security deposit & security cheques --}}
            <section data-step="3" x-show="step === 3" x-cloak class="space-y-4">
                <p class="text-sm text-gray-600" x-show="isDistributor">Marketing policy: security deposit of <strong>₹{{ number_format(Dealer::DISTRIBUTOR_SECURITY_DEPOSIT) }}</strong> and <strong>2 security cheques</strong> from a <strong>current account</strong> in a <strong>nationalised bank</strong>.</p>
                <p class="text-sm text-gray-600" x-show="!isDistributor" x-cloak>Dealer agreement: security deposit of the agreed amount through RTGS / NEFT. Fill in what the dealer has given; these are optional for a dealer.</p>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Security deposit</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <x-dealer-field label="Amount (₹)" name="security_deposit_amount">
                            <input id="security_deposit_amount" name="security_deposit_amount" class="input" inputmode="numeric" pattern="[0-9]+" maxlength="9"
                                   value="{{ $v('security_deposit_amount', $isEdit ? null : Dealer::DISTRIBUTOR_SECURITY_DEPOSIT) }}" :required="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Paid by" name="security_deposit_mode">
                            <select id="security_deposit_mode" name="security_deposit_mode" class="input" :required="isDistributor">
                                <option value="">Choose…</option>
                                @foreach (Dealer::DEPOSIT_MODES as $m)
                                    <option @selected($v('security_deposit_mode') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                        </x-dealer-field>
                        <x-dealer-field label="UTR / DD / cheque no." name="security_deposit_ref">
                            <input id="security_deposit_ref" name="security_deposit_ref" class="input font-mono" maxlength="60" value="{{ $v('security_deposit_ref') }}" :required="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Date paid" name="security_deposit_date">
                            <input id="security_deposit_date" name="security_deposit_date" class="input" type="date" max="{{ date('Y-m-d') }}" value="{{ $date('security_deposit_date') }}" :required="isDistributor">
                        </x-dealer-field>
                    </div>
                    <p class="text-xs text-gray-500">Photograph the deposit receipt in the next steps.</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Security cheques <span class="text-sm font-normal text-gray-500" x-text="isDistributor ? '(2 cheques, current account, nationalised bank only)' : '(optional for a dealer)'"></span></h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Account holder (firm) name" name="bank_account_name">
                            <input id="bank_account_name" name="bank_account_name" class="input" maxlength="120" value="{{ $v('bank_account_name') }}" :required="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Bank" name="bank_name" hint="Nationalised banks only">
                            <select id="bank_name" name="bank_name" class="input" :required="isDistributor">
                                <option value="">Choose…</option>
                                @foreach (Dealer::NATIONALISED_BANKS as $b)
                                    <option @selected($v('bank_name') === $b)>{{ $b }}</option>
                                @endforeach
                                @if ($v('bank_name') && ! in_array($v('bank_name'), Dealer::NATIONALISED_BANKS))
                                    <option selected>{{ $v('bank_name') }}</option>
                                @endif
                            </select>
                        </x-dealer-field>
                        <x-dealer-field label="Branch" name="bank_branch">
                            <input id="bank_branch" name="bank_branch" class="input" maxlength="120" value="{{ $v('bank_branch') }}">
                        </x-dealer-field>
                        <x-dealer-field label="IFSC" name="bank_ifsc">
                            <input id="bank_ifsc" name="bank_ifsc" class="input uppercase font-mono" maxlength="11" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" value="{{ $v('bank_ifsc') }}" title="11-character IFSC" autocapitalize="characters" :required="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Current account number" name="bank_account_no" :hint="$isEdit && $dealer->bank_account_no ? 'Saved: '.$dealer->maskedBankAccount().'. Leave blank to keep it.' : null" class="sm:col-span-2">
                            <input id="bank_account_no" name="bank_account_no" class="input font-mono" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" value="{{ old('bank_account_no') }}" autocomplete="off" title="6 to 20 digits"
                                   :required="isDistributor && !isEdit">
                        </x-dealer-field>
                        <x-dealer-field label="Cheque 1 number" name="cheque_1_no">
                            <input id="cheque_1_no" name="cheque_1_no" class="input font-mono" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ $v('cheque_1_no') }}" title="6-digit cheque number" :required="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Cheque 2 number" name="cheque_2_no">
                            <input id="cheque_2_no" name="cheque_2_no" class="input font-mono" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ $v('cheque_2_no') }}" title="6-digit cheque number" :required="isDistributor">
                        </x-dealer-field>
                    </div>
                    @unless ($isEdit)
                        <label class="flex items-start gap-3 rounded-lg bg-gray-50 p-3" x-show="isDistributor">
                            <input type="checkbox" name="current_account" value="1" class="mt-1 rounded text-brand-600 focus:ring-brand-500" :required="isDistributor" :disabled="!isDistributor">
                            <span class="text-sm text-gray-700">I have checked that both cheques are from the firm's <strong>current account</strong>.</span>
                        </label>
                        <p class="text-sm text-red-600" x-show="err('current_account')" x-text="err('current_account')" x-cloak></p>
                    @endunless
                </div>
            </section>

            {{-- 5. Company officials ("TM & Above" block) --}}
            <section data-step="4" x-show="step === 4" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Field employee</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Emp. code" name="employee_code" required :hint="'Of '.auth()->user()->name">
                            <input id="employee_code" name="employee_code" class="input uppercase font-mono" required maxlength="30" value="{{ $v('employee_code') }}" autocapitalize="characters">
                        </x-dealer-field>
                        <x-dealer-field label="Manager's name" name="manager_name">
                            <input id="manager_name" name="manager_name" class="input" maxlength="120" value="{{ $v('manager_name', $manager?->name) }}">
                        </x-dealer-field>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Appointing manager <span class="text-sm font-normal text-gray-500">(TM & above)</span></h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Name" name="appointing_manager_name" required>
                            <input id="appointing_manager_name" name="appointing_manager_name" class="input" required maxlength="120" value="{{ $v('appointing_manager_name', $manager?->name) }}">
                        </x-dealer-field>
                        <x-dealer-field label="Employee code" name="appointing_manager_code" required>
                            <input id="appointing_manager_code" name="appointing_manager_code" class="input uppercase font-mono" required maxlength="30" value="{{ $v('appointing_manager_code') }}" autocapitalize="characters">
                        </x-dealer-field>
                        <x-dealer-field label="Designation" name="appointing_manager_designation">
                            <input id="appointing_manager_designation" name="appointing_manager_designation" class="input" maxlength="120" value="{{ $v('appointing_manager_designation') }}" placeholder="TM, ASM, RSM…">
                        </x-dealer-field>
                        <x-dealer-field label="Mobile no." name="appointing_manager_mobile">
                            <input id="appointing_manager_mobile" name="appointing_manager_mobile" class="input" type="tel" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" value="{{ $v('appointing_manager_mobile', $manager?->phone) }}" title="10-digit mobile number">
                        </x-dealer-field>
                        <x-dealer-field label="Area" name="appointing_manager_area">
                            <input id="appointing_manager_area" name="appointing_manager_area" class="input" maxlength="120" value="{{ $v('appointing_manager_area', $manager?->territory) }}">
                        </x-dealer-field>
                        <x-dealer-field label="State" name="appointing_manager_state">
                            <select id="appointing_manager_state" name="appointing_manager_state" class="input">
                                <option value="">Choose…</option>
                                @foreach (Dealer::STATES as $s)
                                    <option @selected($v('appointing_manager_state') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </x-dealer-field>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Reporting head</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Name" name="reporting_head_name">
                            <input id="reporting_head_name" name="reporting_head_name" class="input" maxlength="120" value="{{ $v('reporting_head_name') }}">
                        </x-dealer-field>
                        <x-dealer-field label="Designation" name="reporting_head_designation">
                            <input id="reporting_head_designation" name="reporting_head_designation" class="input" maxlength="120" value="{{ $v('reporting_head_designation') }}">
                        </x-dealer-field>
                    </div>
                </div>
            </section>

            {{-- 6. Photos & documents --}}
            <section data-step="5" x-show="step === 5" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h3 class="font-semibold">On-site photos</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Taken with the camera now. Each photo is stamped with your GPS position and the time.</p>
                    <p class="text-sm text-red-600 mt-2" x-show="err('documents')" x-text="err('documents')"></p>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        @foreach (DealerDocument::PHOTO_CATEGORIES as $cat)
                            <label class="relative cursor-pointer rounded-xl border-2 border-dashed p-3 text-center text-sm"
                                   :class="docsIn('{{ $cat }}') ? 'border-brand-400 bg-brand-50' : 'border-gray-300'">
                                <input type="file" accept="image/*" capture="environment" class="sr-only" @change="addFiles($event, '{{ $cat }}')">
                                <svg class="w-7 h-7 mx-auto text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 0 1 2-2h2l2-2h6l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.5"/></svg>
                                <span class="block mt-1 font-medium">{{ DealerDocument::CATEGORIES[$cat] }}@if($cat === 'shop_front' && ! $isEdit)<span class="text-red-600"> *</span>@endif</span>
                                <span class="block text-xs text-brand-700" x-show="docsIn('{{ $cat }}')" x-text="`${docsIn('{{ $cat }}')} added`"></span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                    <h3 class="font-semibold">Required documents</h3>
                    <p class="text-xs text-gray-500">From the marketing policy. Photograph the original or upload a PDF. For Aadhaar, upload only a <strong>masked</strong> copy (first 8 digits hidden). Licence copies are needed for the licences the firm holds.</p>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <template x-for="(count, cat) in requiredDocs" :key="cat">
                            <li class="py-2 flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full grid place-items-center text-xs text-white" :class="haveDocs(cat) >= count ? 'bg-brand-600' : 'bg-gray-300'" x-text="haveDocs(cat) >= count ? '✓' : ''"></span>
                                    <span x-text="categories[cat] + (count > 1 ? ` (${count})` : '')"></span>
                                </span>
                                <label class="text-brand-700 font-medium cursor-pointer shrink-0">
                                    <input type="file" accept="image/*,application/pdf" multiple class="sr-only" @change="addFiles($event, cat)">
                                    <span x-text="haveDocs(cat) ? `${haveDocs(cat)} · Add` : 'Add'"></span>
                                </label>
                            </li>
                        </template>
                    </ul>
                    <div class="flex gap-2 pt-2 border-t">
                        <select class="input" x-model="newDocCategory">
                            @foreach (DealerDocument::CATEGORIES as $key => $label)
                                @continue(in_array($key, DealerDocument::PHOTO_CATEGORIES) || $key === 'signature')
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="btn-secondary shrink-0 cursor-pointer">
                            <input type="file" accept="image/*,application/pdf" multiple class="sr-only" @change="addFiles($event, newDocCategory)">
                            Add other
                        </label>
                    </div>
                </div>

                <div x-show="docs.length" x-cloak class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <template x-for="(doc, i) in docs" :key="doc.key">
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-show="doc.category !== 'signature'">
                            <template x-if="doc.preview"><img :src="doc.preview" class="w-full h-28 object-cover" alt=""></template>
                            <template x-if="!doc.preview"><div class="w-full h-28 grid place-items-center bg-gray-50 text-gray-500 text-sm">PDF</div></template>
                            <div class="p-2 text-xs">
                                <p class="font-medium truncate" x-text="categories[doc.category]"></p>
                                <p class="text-gray-500" x-show="doc.geo" x-text="doc.geo ? `±${Math.round(doc.geo.accuracy)} m GPS` : ''"></p>
                                <p class="text-red-600" x-show="err(`documents.${i}.file`)" x-text="err(`documents.${i}.file`)"></p>
                                <button type="button" class="text-red-600 mt-1" @click="removeDoc(i)">Remove</button>
                            </div>
                        </div>
                    </template>
                </div>

                @if ($isEdit && $dealer->documents()->exists())
                    <p class="text-sm text-gray-600">{{ $dealer->documents()->count() }} file(s) already uploaded. Anything you add here is kept alongside them.</p>
                @endif
            </section>

            {{-- 7. Marketing policy (distributor) / dealer agreement (dealer), and signature --}}
            <section data-step="6" x-show="step === 6" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3" x-show="isDistributor">
                    <h3 class="font-semibold">Marketing policy, FY 2026-27 <span class="text-sm font-normal text-gray-500">(विपणन नीति)</span></h3>
                    <p class="text-xs text-gray-500">Read this with the distributor before they sign.</p>
                    <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-relaxed">
                        @include('dealers.partials.marketing-policy')
                    </div>

                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-1">Cash discount policy chosen <span class="text-red-600">*</span></legend>
                        <p class="text-xs text-gray-500 mb-2">आप कितने प्रतिशत डिस्काउंट पॉलिसी में आना चाहते हैं, यह पहले से तय रहेगा।</p>
                        <div class="space-y-2">
                            @foreach (Dealer::CASH_DISCOUNT_SLABS as $key => $slab)
                                <label class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <span class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="cash_discount_slab" value="{{ $key }}" class="text-brand-600 focus:ring-brand-500" @checked($v('cash_discount_slab') === $key)
                                               :required="isDistributor" :disabled="!isDistributor">
                                        {{ $slab['label'] }}
                                    </span>
                                    <span class="font-semibold">{{ $slab['rate'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-sm text-red-600" x-show="err('cash_discount_slab')" x-text="err('cash_discount_slab')" x-cloak></p>
                    </fieldset>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3" x-show="!isDistributor" x-cloak>
                    <h3 class="font-semibold">Dealer agreement</h3>
                    <p class="text-xs text-gray-500">Read this with the dealer before they sign. Their details from the earlier steps are filled in.</p>
                    <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-relaxed">
                        @include('dealers.partials.dealer-agreement')
                    </div>
                    <x-dealer-field label="Agreement date" name="agreement_date" required>
                        <input id="agreement_date" name="agreement_date" class="input" type="date" value="{{ $date('agreement_date') }}" :required="!isDistributor" :disabled="isDistributor">
                    </x-dealer-field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Witness 1: name" name="witness1_name" required>
                            <input id="witness1_name" name="witness1_name" class="input" maxlength="120" value="{{ $v('witness1_name') }}" :required="!isDistributor" :disabled="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Witness 1: address" name="witness1_address">
                            <input id="witness1_address" name="witness1_address" class="input" maxlength="200" value="{{ $v('witness1_address') }}" :disabled="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Witness 2: name" name="witness2_name" required>
                            <input id="witness2_name" name="witness2_name" class="input" maxlength="120" value="{{ $v('witness2_name') }}" :required="!isDistributor" :disabled="isDistributor">
                        </x-dealer-field>
                        <x-dealer-field label="Witness 2: address" name="witness2_address">
                            <input id="witness2_address" name="witness2_address" class="input" maxlength="200" value="{{ $v('witness2_address') }}" :disabled="isDistributor">
                        </x-dealer-field>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h3 class="font-semibold">Signature & stamp of the <span x-text="isDistributor ? 'distributor' : 'dealer'"></span></h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-dealer-field label="Signing person's name" name="signatory_name" required>
                            <input id="signatory_name" name="signatory_name" class="input" required maxlength="120" value="{{ $v('signatory_name') }}">
                        </x-dealer-field>
                        <x-dealer-field label="Signing as" name="signatory_designation" required>
                            <select id="signatory_designation" name="signatory_designation" class="input" required>
                                <option value="">Choose…</option>
                                @foreach (Dealer::SIGNATORY_DESIGNATIONS as $d)
                                    <option @selected($v('signatory_designation') === $d)>{{ $d }}</option>
                                @endforeach
                            </select>
                        </x-dealer-field>
                    </div>

                    <div>
                        <p class="block text-sm font-medium text-gray-700">Signature @unless($isEdit)<span class="text-red-600">*</span>@endunless</p>
                        <template x-if="signature">
                            <div class="mt-1 space-y-2">
                                <img :src="signature.preview" alt="Signature" class="w-full h-40 object-contain rounded-lg border border-gray-200 bg-white">
                                <button type="button" class="btn-secondary py-1.5" @click="clearSignature()">Sign again</button>
                            </div>
                        </template>
                        <div x-show="!signature" class="mt-1 space-y-2">
                            <canvas x-ref="signaturePad" class="w-full h-40 rounded-lg border-2 border-dashed border-gray-300 bg-white touch-none"
                                    @pointerdown="padStart($event)" @pointermove="padMove($event)" @pointerup="padEnd()" @pointerleave="padEnd()"></canvas>
                            <div class="flex gap-2">
                                <button type="button" class="btn-secondary py-1.5" @click="padClear()">Clear</button>
                                <button type="button" class="btn-primary py-1.5" @click="saveSignature()" :disabled="!padDirty">Use this signature</button>
                            </div>
                            <p class="text-xs text-gray-500">The dealer / distributor signs here with a finger. This replaces signing each page of the paper form.</p>
                        </div>
                        <p class="mt-1 text-sm text-red-600" x-show="err('signature')" x-text="err('signature')" x-cloak></p>
                        @if ($isEdit && $dealer->documents()->where('category', 'signature')->exists())
                            <p class="mt-1 text-xs text-gray-500">A signature is already on file. Sign again only to replace it.</p>
                        @endif
                    </div>

                    <label class="flex items-center justify-between gap-3 rounded-lg border border-dashed border-gray-300 p-3 cursor-pointer text-sm">
                        <span>Firm stamp / seal <span class="text-gray-500">(photo of the stamp on paper)</span></span>
                        <input type="file" accept="image/*" capture="environment" class="sr-only" @change="addFiles($event, 'stamp')">
                        <span class="text-brand-700 font-medium" x-text="docsIn('stamp') ? 'Added ✓' : 'Add photo'"></span>
                    </label>

                    @unless ($isEdit)
                        <label class="flex items-start gap-3 rounded-lg bg-gray-50 p-3">
                            <input type="checkbox" name="terms_accepted" value="1" required class="mt-1 rounded text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-gray-700" x-show="isDistributor">हमने/मैंने विपणन नीति 2026-27 को ठीक से पढ़ कर व पूरी तरह समझ कर ही सभी शर्तों से सहमत है/हूँ। मैं/हम वचन देते/देती है/हूँ कि कम्पनी के स्टाफ से किसी भी प्रकार का नकद लेन-देन नहीं करूंगा/करूंगी व न ही किसी प्रकार के माल का आदान-प्रदान करूंगा/करूंगी। वचन के हनन की स्थिति में कम्पनी कानूनी कार्यवाही कर सकती है व इस शपथ को भी मेरे विरूद्ध प्रयोग कर सकती है।</span>
                            <span class="text-sm text-gray-700" x-show="!isDistributor" x-cloak>The dealer has read the dealer agreement above and agrees to its terms and conditions.</span>
                        </label>
                        <p class="text-sm text-red-600" x-show="err('terms_accepted')" x-text="err('terms_accepted')" x-cloak></p>
                    @endunless
                </div>
            </section>

            {{-- 8. Confirm --}}
            <section data-step="7" x-show="step === 7" x-cloak class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-gray-500">Form</dt>
                        <dd x-text="`${isDistributor ? 'Distributor' : 'Dealer'} · ${snapshot.application_type === 'renewal' ? 'Renewal' : 'New'} · ${snapshot.financial_year || ''}`"></dd>
                        <dt class="text-gray-500">Firm</dt>
                        <dd x-text="snapshot.business_name || '—'"></dd>
                        <dt class="text-gray-500">GPS</dt>
                        <dd :class="geo ? 'text-brand-700' : 'text-red-600'" x-text="geo ? `Captured (±${Math.round(geo.accuracy || 0)} m)` : 'Not captured'"></dd>
                        <dt class="text-gray-500">Required documents</dt>
                        <dd x-text="`${requiredDone} of ${Object.keys(requiredDocs).length}`"></dd>
                        <dt class="text-gray-500">New photos / files</dt>
                        <dd x-text="docs.length"></dd>
                        <dt class="text-gray-500">Signature</dt>
                        <dd :class="signature || isEdit ? 'text-brand-700' : 'text-red-600'" x-text="signature ? 'Signed' : (isEdit ? 'On file' : 'Not signed')"></dd>
                    </dl>

                    <x-dealer-field label="Visit notes" name="visit_notes" hint="Shop condition, stock seen, business potential, anything the reviewer should know">
                        <textarea id="visit_notes" name="visit_notes" class="input" rows="4" maxlength="2000">{{ $v('visit_notes') }}</textarea>
                    </x-dealer-field>

                    @unless ($isEdit)
                        <label class="flex items-start gap-3 rounded-lg bg-gray-50 p-3">
                            <input type="checkbox" name="consent" value="1" required class="mt-1 rounded text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-gray-700">The dealer / distributor has agreed that MD Biocoals may collect these details and documents and verify them for registration, under the Digital Personal Data Protection Act, 2023.</span>
                        </label>
                        <p class="text-sm text-red-600" x-show="err('consent')" x-text="err('consent')"></p>
                    @endunless
                </div>
            </section>

            {{-- Navigation --}}
            <div class="sticky bottom-16 md:bottom-0 mt-4 -mx-4 sm:mx-0 bg-gray-100/95 backdrop-blur px-4 sm:px-0 py-3 flex gap-3">
                <button type="button" class="btn-secondary flex-1 py-3" @click="back()" x-show="step > 0">Back</button>
                <button type="button" class="btn-primary flex-[2] py-3" @click="next()" x-show="step < steps.length - 1">Next</button>
                <button type="submit" class="btn-primary flex-[2] py-3" x-show="step === steps.length - 1" x-cloak :disabled="submitting">
                    <span x-show="!submitting">{{ $isEdit ? 'Save changes' : 'Submit form' }}</span>
                    <span x-show="submitting" x-text="progress < 100 ? `Uploading ${progress}%` : 'Saving…'"></span>
                </button>
            </div>
            <p class="text-center text-xs text-gray-500" x-show="draftSavedAt && !isEdit" x-cloak x-text="draftSavedAt ? 'Draft saved on this device at ' + draftSavedAt.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'}) : ''"></p>
        </form>
    </div>
</x-app-layout>
