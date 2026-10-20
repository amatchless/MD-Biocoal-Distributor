@props(['status'])
@php
    $classes = [
        'submitted' => 'bg-sky-100 text-sky-800',
        'under_review' => 'bg-amber-100 text-amber-800',
        'needs_info' => 'bg-orange-100 text-orange-800',
        'approved' => 'bg-brand-100 text-brand-800',
        'rejected' => 'bg-red-100 text-red-800',
    ][$status] ?? 'bg-gray-100 text-gray-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap $classes"]) }}>
    {{ \App\Models\Dealer::STATUSES[$status] ?? $status }}
</span>
