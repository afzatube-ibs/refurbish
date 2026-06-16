@php
    $health = $health ?? ['status' => 'ok', 'label' => 'OK', 'issues' => []];
    $healthIssues = $healthIssues ?? ($health['issues'] ?? []);
    $healthTitle = $healthTitle ?? ($healthIssues !== [] ? implode('; ', $healthIssues) : 'No issues');
    $healthStatus = $health['status'] ?? 'ok';
    $healthLabel = $health['label'] ?? 'OK';
    if ($healthStatus === 'low') {
        $healthStatus = 'alert';
        $healthLabel = 'Alert';
    }
@endphp

@if ($healthStatus === 'ok')
    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">OK</span>
@elseif ($healthStatus === 'critical')
    <div class="inline-flex flex-col items-center gap-0.5" title="{{ $healthTitle }}">
        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ $healthLabel }}</span>
        <span class="text-[10px] leading-tight text-red-700 max-w-[8rem] truncate">{{ $healthIssues[0] ?? 'Critical' }}</span>
    </div>
@elseif ($healthStatus === 'warning')
    <div class="inline-flex flex-col items-center gap-0.5" title="{{ $healthTitle }}">
        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{{ $healthLabel }}</span>
        <span class="text-[10px] leading-tight text-amber-700 max-w-[8rem] truncate">{{ $healthIssues[0] ?? 'Warning' }}</span>
    </div>
@elseif ($healthStatus === 'alert')
    <div class="inline-flex flex-col items-center gap-0.5" title="{{ $healthTitle }}">
        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">{{ $healthLabel }}</span>
        <span class="text-[10px] leading-tight text-yellow-700 max-w-[8rem] truncate">{{ $healthIssues[0] ?? 'Alert' }}</span>
    </div>
@else
    <div class="inline-flex flex-col items-center gap-0.5" title="{{ $healthTitle }}">
        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{{ $healthLabel }}</span>
        <span class="text-[10px] leading-tight text-amber-700 max-w-[8rem] truncate">{{ $healthIssues[0] ?? 'Review' }}</span>
    </div>
@endif
