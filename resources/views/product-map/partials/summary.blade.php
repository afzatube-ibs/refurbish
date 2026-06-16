@php
    $totalProducts = (int) ($previewSummary['warehouse_preview'] ?? 0);
    $readyCount = (int) ($previewSummary['health_ok'] ?? 0);
    $variantRows = (int) ($previewSummary['variant_rows'] ?? 0);
    $needsWork = max(0, $totalProducts - $readyCount);
@endphp

<div class="pm-stats-grid">
    <div class="pm-stat-card">
        <div class="pm-stat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div class="pm-stat-body">
            <p class="pm-stat-label">Total Products</p>
            <p class="pm-stat-value">{{ number_format($totalProducts) }}</p>
            <p class="pm-stat-sub">Loaded preview rows</p>
        </div>
    </div>

    <div class="pm-stat-card">
        <div class="pm-stat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
        </div>
        <div class="pm-stat-body">
            <p class="pm-stat-label">Ready</p>
            <p class="pm-stat-value">{{ number_format($readyCount) }}</p>
            <p class="pm-stat-sub">Health OK</p>
        </div>
    </div>

    <div class="pm-stat-card">
        <div class="pm-stat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
        </div>
        <div class="pm-stat-body">
            <p class="pm-stat-label">Variants</p>
            <p class="pm-stat-value">{{ number_format($variantRows) }}</p>
            <p class="pm-stat-sub">Option rows loaded</p>
        </div>
    </div>

    <div class="pm-stat-card">
        <div class="pm-stat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div class="pm-stat-body">
            <p class="pm-stat-label">Needs Work</p>
            <p class="pm-stat-value">{{ number_format($needsWork) }}</p>
            <p class="pm-stat-sub">Needs review</p>
        </div>
    </div>
</div>
