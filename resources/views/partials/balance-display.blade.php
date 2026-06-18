@php
    $amount = round((float) ($amount ?? 0), 2);
    $meaning = $meaning ?? app(\App\Services\PayableService::class)->balanceMeaning($amount);
    $toneClass = $toneClass ?? app(\App\Services\PayableService::class)->balanceToneClass($amount);
    $amountClass = trim(($amountClass ?? 'text-2xl font-semibold').' tabular-nums '.$toneClass);
    $meaningClass = $meaningClass ?? 'text-xs text-slate-500 mt-1';
@endphp
<div>
    <p class="{{ $amountClass }}">{{ number_format($amount, 2) }}</p>
    @if ($showMeaning ?? true)
        <p class="{{ $meaningClass }}">{{ $meaning }}</p>
    @endif
</div>
