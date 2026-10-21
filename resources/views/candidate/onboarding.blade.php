@php
    use App\Models\EmployeeOnboarding;
    use App\Models\OnboardingDocument;
    $o = $onboarding;
    $editable = $o->candidateCanEdit();
    $required = OnboardingDocument::requiredFor($o);
    $have = $o->documents->groupBy('category');
    $education = old('education', $o->education ?: [['qualification' => '', 'institution' => '', 'year' => '', 'score' => '']]);
    $employment = old('employment', $o->employment ?: [['company' => '', 'designation' => '', 'from' => '', 'to' => '', 'contact_name' => '', 'contact_phone' => '']]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#065f46">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Joining details · MD Biocoals</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-900">
    <header class="bg-brand-800 text-white">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center gap-3">
            <span class="bg-white rounded-lg px-2 py-1"><x-application-logo class="h-10 w-auto" /></span>
            <span class="font-semibold">Joining details</span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-5 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h1 class="text-lg font-bold">Welcome, {{ $o->candidate_name }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                @if ($o->position){{ $o->position->name }}, @endif{{ $o->department?->name }} department @if ($o->joining_date)· joining {{ $o->joining_date->format('d M Y') }}@endif
            </p>
            <p class="text-xs text-gray-400 mt-1">Reference {{ $o->ref_no }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg bg-brand-50 border border-brand-200 text-brand-800 px-4 py-3 text-sm" role="status">{{ session('status') }}</div>
        @endif

        @if (! $editable)
            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                @if (in_array($o->status, EmployeeOnboarding::CANDIDATE_EDITABLE, true))
                    <p class="font-semibold">This link has expired.</p>
                    <p class="text-sm text-gray-600 mt-1">Please contact the MD Biocoals HR team for a new link.</p>
                @else
                    <p class="font-semibold">Your details have been submitted.</p>
                    <p class="text-sm text-gray-600 mt-1">The HR team is verifying your documents and will contact you if anything else is needed.</p>
                @endif
            </div>
        @else
            @if ($o->status === 'info_pending' && $o->info_request)
                <div class="rounded-xl bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-900">
                    <p class="font-semibold">HR needs the following from you</p>
                    <p class="mt-1 whitespace-pre-line">{{ $o->info_request }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-900">
                    <p class="font-semibold">Please check the highlighted fields.</p>
                    @error('documents')<p class="mt-1">{{ $message }}</p>@enderror
                </div>
            @endif

            <p class="text-sm text-gray-600">Fill in every section and upload your documents. You can <strong>save</strong> and come back to this link later, until {{ local_time($o->access_expires_at, 'd M Y') }}. Press <strong>Submit</strong> when everything is ready.</p>

            <form method="POST" action="{{ route('candidate.save', $token) }}" enctype="multipart/form-data" class="space-y-4"
                  x-data="{ fresher: @js((bool) old('is_fresher', $o->is_fresher)), education: @js(array_values($education)), employment: @js(array_values($employment)) }">
                @csrf

                <x-card title="1. Personal details">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-field label="Father's / spouse's name" name="father_name" required>
                            <input id="father_name" name="father_name" class="input" maxlength="120" value="{{ old('father_name', $o->father_name) }}">
                        </x-field>
                        <x-field label="Date of birth" name="date_of_birth" required>
                            <input id="date_of_birth" name="date_of_birth" type="date" class="input" value="{{ old('date_of_birth', $o->date_of_birth?->toDateString()) }}">
                        </x-field>
                        <x-field label="Gender" name="gender" required>
                            <select id="gender" name="gender" class="input">
                                <option value="">Choose…</option>
                                @foreach (EmployeeOnboarding::GENDERS as $g)<option @selected(old('gender', $o->gender) === $g)>{{ $g }}</option>@endforeach
                            </select>
                        </x-field>
                        <x-field label="Alternate mobile" name="alternate_phone">
                            <input id="alternate_phone" name="alternate_phone" type="tel" inputmode="numeric" maxlength="10" class="input" value="{{ old('alternate_phone', $o->alternate_phone) }}">
                        </x-field>
                        <x-field label="PAN" name="pan_no" required>
                            <input id="pan_no" name="pan_no" class="input uppercase font-mono" maxlength="10" value="{{ old('pan_no', $o->pan_no) }}" placeholder="ABCDE1234F">
                        </x-field>
                        <x-field label="Aadhaar: last 4 digits only" name="aadhaar_last4" required hint="Do not enter your full Aadhaar number.">
                            <input id="aadhaar_last4" name="aadhaar_last4" inputmode="numeric" maxlength="4" class="input font-mono" value="{{ old('aadhaar_last4', $o->aadhaar_last4) }}" placeholder="1234">
                        </x-field>
                    </div>
                </x-card>

                <x-card title="2. Address">
                    <div class="grid sm:grid-cols-3 gap-4">
                        <x-field label="Current address" name="current_address" required class="sm:col-span-3">
                            <textarea id="current_address" name="current_address" rows="2" maxlength="500" class="input">{{ old('current_address', $o->current_address) }}</textarea>
                        </x-field>
                        <x-field label="City / town" name="city" required>
                            <input id="city" name="city" class="input" maxlength="80" value="{{ old('city', $o->city) }}">
                        </x-field>
                        <x-field label="State" name="state" required>
                            <select id="state" name="state" class="input">
                                <option value="">Choose…</option>
                                @foreach (\App\Models\Dealer::STATES as $s)<option @selected(old('state', $o->state) === $s)>{{ $s }}</option>@endforeach
                            </select>
                        </x-field>
                        <x-field label="PIN code" name="pincode" required>
                            <input id="pincode" name="pincode" inputmode="numeric" maxlength="6" class="input" value="{{ old('pincode', $o->pincode) }}">
                        </x-field>
                        <x-field label="Permanent address, if different" name="permanent_address" class="sm:col-span-3">
                            <textarea id="permanent_address" name="permanent_address" rows="2" maxlength="500" class="input">{{ old('permanent_address', $o->permanent_address) }}</textarea>
                        </x-field>
                    </div>
                </x-card>

                <x-card title="3. Education" subtitle="Start with your highest qualification.">
                    <template x-for="(row, i) in education" :key="i">
                        <div class="grid sm:grid-cols-12 gap-2 mb-3 pb-3 border-b border-gray-100">
                            <input class="input sm:col-span-3" :name="`education[${i}][qualification]`" x-model="row.qualification" placeholder="e.g. B.Sc. Agriculture" maxlength="120">
                            <input class="input sm:col-span-5" :name="`education[${i}][institution]`" x-model="row.institution" placeholder="College / board / university" maxlength="150">
                            <input class="input sm:col-span-2" :name="`education[${i}][year]`" x-model="row.year" inputmode="numeric" maxlength="4" placeholder="Year">
                            <input class="input sm:col-span-2" :name="`education[${i}][score]`" x-model="row.score" maxlength="20" placeholder="% / CGPA">
                        </div>
                    </template>
                    @error('education')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror
                    @if ($errors->has('education.*'))<p class="text-sm text-red-600 mb-2">Each qualification needs a name, institution and year.</p>@endif
                    <button type="button" class="text-sm text-brand-700 font-medium" x-show="education.length < 6" @click="education.push({ qualification: '', institution: '', year: '', score: '' })">+ Add another qualification</button>
                </x-card>

                <x-card title="4. Previous employment">
                    <label class="flex items-center gap-2 mb-3">
                        <input type="hidden" name="is_fresher" value="0">
                        <input type="checkbox" name="is_fresher" value="1" x-model="fresher" class="rounded text-brand-600 focus:ring-brand-500">
                        <span class="text-sm">I am a fresher (no previous job)</span>
                    </label>
                    <div x-show="! fresher">
                        <template x-for="(row, i) in employment" :key="i">
                            <div class="grid sm:grid-cols-6 gap-2 mb-3 pb-3 border-b border-gray-100">
                                <input class="input sm:col-span-3" :name="`employment[${i}][company]`" x-model="row.company" placeholder="Company" maxlength="150">
                                <input class="input sm:col-span-3" :name="`employment[${i}][designation]`" x-model="row.designation" placeholder="Designation" maxlength="120">
                                <label class="text-xs text-gray-500 sm:col-span-3">From <input type="month" class="input" :name="`employment[${i}][from]`" x-model="row.from"></label>
                                <label class="text-xs text-gray-500 sm:col-span-3">To (blank if still working) <input type="month" class="input" :name="`employment[${i}][to]`" x-model="row.to"></label>
                                <input class="input sm:col-span-3" :name="`employment[${i}][contact_name]`" x-model="row.contact_name" placeholder="HR / manager we can contact" maxlength="120">
                                <input class="input sm:col-span-3" :name="`employment[${i}][contact_phone]`" x-model="row.contact_phone" placeholder="Their phone" maxlength="20">
                            </div>
                        </template>
                        @error('employment')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror
                        @if ($errors->has('employment.*'))<p class="text-sm text-red-600 mb-2">Each job needs a company, designation and start month.</p>@endif
                        <button type="button" class="text-sm text-brand-700 font-medium" x-show="employment.length < 6" @click="employment.push({ company: '', designation: '', from: '', to: '', contact_name: '', contact_phone: '' })">+ Add another job</button>
                    </div>
                </x-card>

                <x-card title="5. Emergency contact & bank">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-field label="Emergency contact name" name="emergency_contact_name" required>
                            <input id="emergency_contact_name" name="emergency_contact_name" class="input" maxlength="120" value="{{ old('emergency_contact_name', $o->emergency_contact_name) }}">
                        </x-field>
                        <x-field label="Emergency contact mobile" name="emergency_contact_phone" required>
                            <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" inputmode="numeric" maxlength="10" class="input" value="{{ old('emergency_contact_phone', $o->emergency_contact_phone) }}">
                        </x-field>
                        <x-field label="Bank name" name="bank_name" required>
                            <input id="bank_name" name="bank_name" class="input" maxlength="120" value="{{ old('bank_name', $o->bank_name) }}">
                        </x-field>
                        <x-field label="IFSC" name="bank_ifsc" required>
                            <input id="bank_ifsc" name="bank_ifsc" class="input uppercase font-mono" maxlength="11" value="{{ old('bank_ifsc', $o->bank_ifsc) }}" placeholder="SBIN0001234">
                        </x-field>
                        <x-field label="Account number" name="bank_account_no" required class="sm:col-span-2"
                                 :hint="$o->bank_account_no ? 'Saved: '.$o->maskedBankAccount().'. Leave blank to keep it.' : null">
                            <input id="bank_account_no" name="bank_account_no" inputmode="numeric" maxlength="18" class="input font-mono" autocomplete="off">
                        </x-field>
                    </div>
                </x-card>

                <x-card title="6. Documents" subtitle="Photos or PDFs, up to 10 MB each. Take clear photos in good light.">
                    <ul class="divide-y divide-gray-100">
                        @foreach (OnboardingDocument::CATEGORIES as $cat => $label)
                            @php $isRequired = in_array($cat, $required, true); @endphp
                            <li class="py-3" @if ($cat === 'experience_letter') x-show="! fresher" @endif>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">
                                            {{ $label }}
                                            @if ($isRequired)<span class="text-red-600" @if ($cat === 'experience_letter') x-show="! fresher" @endif>*</span>@endif
                                            @if ($have->has($cat))<span class="ml-1 text-brand-700">✓</span>@endif
                                        </p>
                                        @if (isset(OnboardingDocument::HINTS[$cat]))<p class="text-xs text-gray-500">{{ OnboardingDocument::HINTS[$cat] }}</p>@endif
                                    </div>
                                </div>
                                @foreach ($have->get($cat, []) as $doc)
                                    <div class="mt-1 flex items-center justify-between gap-2 text-xs bg-gray-50 rounded px-2 py-1">
                                        <span class="truncate">{{ $doc->original_name }}</span>
                                        @if ($doc->uploadedByCandidate())
                                            <button type="submit" form="remove-{{ $doc->id }}" class="text-red-600 underline shrink-0">Remove</button>
                                        @endif
                                    </div>
                                @endforeach
                                <input type="file" name="files[{{ $cat }}][]" multiple accept="image/*,application/pdf" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-800 file:font-medium">
                            </li>
                        @endforeach
                    </ul>
                    @if ($errors->has('files.*') || $errors->has('files.*.*'))<p class="text-sm text-red-600 mt-2">Each file must be a photo or PDF under 10 MB.</p>@endif
                </x-card>

                <x-card title="7. Declaration & consent">
                    <div class="text-sm text-gray-700 space-y-2">
                        <p>I confirm that the information and documents I have given are true and complete. I understand that false or hidden information may lead to my offer being withdrawn or my employment being ended.</p>
                        <p>I consent to MD Biocoals using these details and documents to verify my identity, address, education and previous employment, including contacting the institutions and employers I have listed, for my recruitment and employment records. My data will be kept securely and used only for these purposes, as required under the Digital Personal Data Protection Act, 2023.</p>
                    </div>
                    <label class="flex items-start gap-2 mt-3">
                        <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded text-brand-600 focus:ring-brand-500" @checked(old('consent'))>
                        <span class="text-sm font-medium">I have read and agree to the above.</span>
                    </label>
                    @error('consent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </x-card>

                <div class="flex gap-2 pb-8">
                    <button name="action" value="save" class="btn-secondary flex-1">Save for later</button>
                    <button name="action" value="submit" class="btn-primary flex-[2]" onclick="return confirm('Submit your details to HR? You will not be able to change them afterwards.')">Submit to HR</button>
                </div>
            </form>

            @foreach ($o->documents->filter->uploadedByCandidate() as $doc)
                <form id="remove-{{ $doc->id }}" method="POST" action="{{ route('candidate.documents.destroy', [$token, $doc]) }}" onsubmit="return confirm('Remove this document?')" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
    </main>
</body>
</html>
