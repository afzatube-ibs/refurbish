@props(['status' => null, 'label' => null])

@php
    $value = $status instanceof \App\Enums\SfmOrderStatus ? $status->value : (string) $status;
    $text = $label ?? ($status instanceof \App\Enums\SfmOrderStatus ? $status->label() : ucfirst(str_replace('_', ' ', $value)));

    $classes = match ($value) {
        'ignore' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'new' => 'bg-blue-100 text-blue-800 ring-blue-200',
        'accepted' => 'bg-indigo-100 text-indigo-800 ring-indigo-200',
        'packed' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'dispatched' => 'bg-violet-100 text-violet-800 ring-violet-200',
        'rejected' => 'bg-red-100 text-red-800 ring-red-200',
        'return_queue' => 'bg-orange-100 text-orange-800 ring-orange-200',
        'return_received' => 'bg-yellow-100 text-yellow-800 ring-yellow-200',
        'completed' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    {{ $text }}
</span>
