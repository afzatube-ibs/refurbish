@props(['check'])

@php
    $status = $check['status'] ?? ($check['passed'] ?? false ? 'connected' : 'failed');
    $label = $check['label'] ?? $check['name'] ?? 'Check';
    $message = $check['message'] ?? '';

    [$icon, $iconClass, $badgeClass] = match ($status) {
        'connected' => ['✓', 'bg-emerald-100 text-emerald-700', 'text-emerald-700'],
        'failed' => ['✗', 'bg-red-100 text-red-700', 'text-red-700'],
        'needs_attention' => ['!', 'bg-amber-100 text-amber-800', 'text-amber-800'],
        'optional' => ['○', 'bg-slate-100 text-slate-500', 'text-slate-500'],
        default => ['·', 'bg-slate-100 text-slate-500', 'text-slate-500'],
    };
@endphp

<li class="flex items-center justify-between gap-3 py-2.5 border-b border-slate-100 last:border-0">
    <div class="flex items-center gap-3 min-w-0">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $iconClass }}">{{ $icon }}</span>
        <span class="text-sm font-medium text-slate-800 truncate">{{ $label }}</span>
    </div>
    <span class="text-xs font-medium shrink-0 {{ $badgeClass }}">{{ $message }}</span>
</li>
