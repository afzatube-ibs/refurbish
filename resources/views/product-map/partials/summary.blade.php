@php
    $totalProducts = (int) ($previewSummary['warehouse_preview'] ?? 0);
    $readyCount = (int) ($previewSummary['health_ok'] ?? 0);
    $variantRows = (int) ($previewSummary['variant_rows'] ?? 0);
    $needsWork = (int) ($previewSummary['health_needs_work'] ?? max(0, $totalProducts - $readyCount));
@endphp

<p class="pm-catalog-notice">Products loaded from local database.</p>

<div class="pm-stats-grid">
    <div class="pm-stat-card pm-stat-card--total">
        <div class="pm-stat-body">
            <p class="pm-stat-label">Products</p>
            <p class="pm-stat-value">{{ number_format($totalProducts) }}</p>
            <p class="pm-stat-sub">Saved products</p>
        </div>
    </div>

    <div class="pm-stat-card pm-stat-card--ready">
        <div class="pm-stat-body">
            <p class="pm-stat-label">Ready</p>
            <p class="pm-stat-value">{{ number_format($readyCount) }}</p>
            <p class="pm-stat-sub">Ready to use</p>
        </div>
    </div>

    <div class="pm-stat-card pm-stat-card--variants">
        <div class="pm-stat-body">
            <p class="pm-stat-label">Variants</p>
            <p class="pm-stat-value">{{ number_format($variantRows) }}</p>
            <p class="pm-stat-sub">Variant rows</p>
        </div>
    </div>

    <div class="pm-stat-card pm-stat-card--work">
        <div class="pm-stat-body">
            <p class="pm-stat-label">Needs Work</p>
            <p class="pm-stat-value">{{ number_format($needsWork) }}</p>
            <p class="pm-stat-sub">Require review</p>
        </div>
    </div>
</div>
