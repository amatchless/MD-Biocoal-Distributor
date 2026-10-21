@php
    use App\Models\OnboardingCheck;
    $o = $onboarding;
    $checks = $o->checks->sortBy(fn ($c) => array_search($c->check_key, array_keys(OnboardingCheck::CHECKS)));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification report {{ $o->ref_no }} · {{ $o->candidate_name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print { .no-print { display: none !important; } body { background: #fff; } .sheet { box-shadow: none; border: 0; margin: 0; max-width: none; } }
        .sheet table td, .sheet table th { vertical-align: top; }
    </style>
</head>
<body class="font-sans bg-gray-100 text-gray-900 text-sm">
    <div class="no-print max-w-4xl mx-auto px-4 pt-4 flex justify-between">
        <a href="{{ route('onboarding.show', $o) }}" class="btn-secondary py-1.5">← Back to case</a>
        <button onclick="window.print()" class="btn-primary py-1.5">Print / save as PDF</button>
    </div>

    <div class="sheet max-w-4xl mx-auto my-4 bg-white border border-gray-200 shadow-sm p-8 space-y-6">
        <header class="flex items-start justify-between gap-4 border-b pb-4">
            <div>
                <x-application-logo class="h-14 w-auto" />
            </div>
            <div class="text-right">
                <h1 class="text-lg font-bold">Employee Background Verification Report</h1>
                <p class="font-mono text-xs text-gray-500">{{ $o->ref_no }}</p>
                <p class="text-xs text-gray-500">Generated {{ local_time(now()) }}</p>
            </div>
        </header>

        <section>
            <h2 class="font-semibold mb-2">1. Case summary</h2>
            <table class="w-full border border-gray-200">
                <tbody class="divide-y divide-gray-200">
                    @foreach ([
                        ['Candidate', $o->candidate_name, 'Department', $o->department?->name],
                        ['Position', $o->position?->label(), 'Work location', $o->work_location],
                        ['Joining date', $o->joining_date?->format('d M Y'), 'Reports to', $o->manager?->name],
                        ['Date of birth', $o->date_of_birth?->format('d M Y'), "Father's / spouse's name", $o->father_name],
                        ['Mobile', $o->candidate_phone, 'Email', $o->candidate_email],
                        ['PAN', $o->pan_no, 'Aadhaar', $o->aadhaar_last4 ? 'XXXX XXXX '.$o->aadhaar_last4 : null],
                        ['Case owner', $o->owner?->name, 'Submitted by candidate', $o->submitted_at ? local_time($o->submitted_at) : null],
                    ] as [$l1, $v1, $l2, $v2])
                        <tr>
                            <th class="text-left font-medium text-gray-500 bg-gray-50 px-2 py-1 w-1/6">{{ $l1 }}</th><td class="px-2 py-1 w-1/3">{{ $v1 ?: '—' }}</td>
                            <th class="text-left font-medium text-gray-500 bg-gray-50 px-2 py-1 w-1/6">{{ $l2 }}</th><td class="px-2 py-1 w-1/3">{{ $v2 ?: '—' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th class="text-left font-medium text-gray-500 bg-gray-50 px-2 py-1">Address</th>
                        <td class="px-2 py-1" colspan="3">{{ $o->current_address ? $o->current_address.', '.$o->city.', '.$o->state.' '.$o->pincode : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="font-semibold mb-2">2. Checks performed</h2>
            <table class="w-full border border-gray-200">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="px-2 py-1 font-medium">Check</th><th class="px-2 py-1 font-medium">Result</th><th class="px-2 py-1 font-medium">Method</th><th class="px-2 py-1 font-medium">Remarks</th><th class="px-2 py-1 font-medium">By / on</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($checks as $c)
                        <tr>
                            <td class="px-2 py-1 font-medium">{{ $c->label() }}</td>
                            <td class="px-2 py-1 whitespace-nowrap {{ in_array($c->status, ['discrepancy', 'unable_to_verify']) ? 'text-red-700 font-semibold' : '' }}">{{ $c->statusLabel() }}</td>
                            <td class="px-2 py-1">{{ $c->method ?: '—' }}</td>
                            <td class="px-2 py-1 whitespace-pre-line">{{ $c->remarks ?: '—' }}</td>
                            <td class="px-2 py-1 text-xs">{{ $c->checker?->name }}<br>{{ local_time($c->checked_at, 'd M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="font-semibold mb-2">3. Education and employment declared</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-500 text-xs mb-1">Education</p>
                    @forelse ($o->education ?? [] as $e)
                        <p>{{ $e['qualification'] ?? '' }}, {{ $e['institution'] ?? '' }} ({{ $e['year'] ?? '' }}){{ ! empty($e['score']) ? ' · '.$e['score'] : '' }}</p>
                    @empty <p>—</p> @endforelse
                </div>
                <div>
                    <p class="text-gray-500 text-xs mb-1">Employment</p>
                    @if ($o->is_fresher)
                        <p>Fresher</p>
                    @else
                        @forelse ($o->employment ?? [] as $e)
                            <p>{{ $e['company'] ?? '' }}, {{ $e['designation'] ?? '' }} ({{ $e['from'] ?? '' }} to {{ ($e['to'] ?? null) ?: 'present' }})</p>
                        @empty <p>—</p> @endforelse
                    @endif
                </div>
            </div>
        </section>

        <section>
            <h2 class="font-semibold mb-2">4. Reference contacts</h2>
            @forelse ($o->references as $r)
                <p>{{ local_time($r->created_at, 'd M Y') }}: {{ $r->contact_name }}{{ $r->organisation ? ', '.$r->organisation : '' }} ({{ $r->kindLabel() }}): <strong>{{ $r->outcomeLabel() }}</strong>{{ $r->notes ? '. '.$r->notes : '' }}</p>
            @empty
                <p class="text-gray-500">None logged.</p>
            @endforelse
        </section>

        <section>
            <h2 class="font-semibold mb-2">5. Evidence on file</h2>
            <p>{{ $o->documents->map->label()->countBy()->map(fn ($n, $label) => $n > 1 ? "$label ($n)" : $label)->implode(', ') ?: 'None' }}</p>
        </section>

        <section>
            <h2 class="font-semibold mb-2">6. Risk assessment</h2>
            @if ($o->risk_level)
                <p><strong>{{ $o->riskLabel() }} risk</strong> · assessed by {{ $o->assessor?->name }} on {{ local_time($o->assessed_at, 'd M Y') }}</p>
                <p class="whitespace-pre-line mt-1">{{ $o->risk_notes }}</p>
            @else
                <p class="text-gray-500">Not assessed yet.</p>
            @endif
        </section>

        <section class="border-t pt-4">
            <h2 class="font-semibold mb-2">7. Reviewer outcome</h2>
            @if ($o->isDecided())
                <p class="text-base"><strong class="{{ $o->status === 'approved' ? 'text-brand-800' : 'text-red-700' }}">{{ strtoupper($o->statusLabel()) }}</strong> by {{ $o->decider?->name }} on {{ local_time($o->decided_at) }}</p>
                @if ($o->decision_notes)<p class="whitespace-pre-line mt-1">{{ $o->decision_notes }}</p>@endif
            @else
                <p class="text-gray-500">Pending: {{ $o->statusLabel() }}.</p>
            @endif
            <p class="text-xs text-gray-400 mt-6">Candidate consent recorded {{ $o->consent_at ? local_time($o->consent_at).' from '.$o->consent_ip : '—' }}. Confidential: for MD Biocoals HR use only.</p>
        </section>
    </div>
</body>
</html>
