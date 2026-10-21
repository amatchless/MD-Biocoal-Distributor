@props(['status'])
@php
    $classes = [
        'invited' => 'bg-sky-100 text-sky-800',
        'info_pending' => 'bg-orange-100 text-orange-800',
        'under_verification' => 'bg-amber-100 text-amber-800',
        'risk_review' => 'bg-violet-100 text-violet-800',
        'approved' => 'bg-brand-100 text-brand-800',
        'rejected' => 'bg-red-100 text-red-800',
    ][$status] ?? 'bg-gray-100 text-gray-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap $classes"]) }}>
    {{ \App\Models\EmployeeOnboarding::STATUSES[$status] ?? $status }}
</span>
