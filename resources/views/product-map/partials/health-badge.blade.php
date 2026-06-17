@php
    $health = $health ?? ['status' => 'ok', 'label' => 'OK', 'issues' => []];
    $healthIssues = $healthIssues ?? ($health['issues'] ?? []);
    $healthTitle = $healthTitle ?? ($healthIssues !== [] ? implode('; ', $healthIssues) : 'Ready — no issues');
    $healthStatus = $health['status'] ?? 'ok';
    $healthLabel = $health['label'] ?? 'OK';
    if ($healthStatus === 'low') {
        $healthStatus = 'alert';
        $healthLabel = 'Alert';
    }
    $statusClass = match ($healthStatus) {
        'ok' => 'pm-health--ok',
        'critical' => 'pm-health--critical',
        'warning' => 'pm-health--warning',
        'alert' => 'pm-health--alert',
        default => 'pm-health--review',
    };
    $shortLabel = match ($healthStatus) {
        'ok' => 'Ready',
        'critical' => 'Critical',
        'warning' => 'Warning',
        'alert' => 'Alert',
        default => 'Review',
    };
@endphp

<span class="pm-health-badge {{ $statusClass }}" title="{{ $healthTitle }}">
    <span class="pm-health-dot" aria-hidden="true"></span>
    <span class="pm-health-text">{{ $shortLabel }}</span>
</span>
