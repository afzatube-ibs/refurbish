<div class="mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Products</p>
        <p class="text-lg font-semibold text-slate-900" data-summary-key="warehouse_preview">{{ $previewSummary['warehouse_preview'] ?? 0 }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">IBS Models</p>
        <p class="text-lg font-semibold text-slate-900" data-summary-key="unique_ibs_models">{{ $previewSummary['unique_ibs_models'] ?? 0 }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Health OK</p>
        <p class="text-lg font-semibold text-emerald-700" data-summary-key="health_ok">{{ $previewSummary['health_ok'] ?? 0 }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Variable</p>
        <p class="text-lg font-semibold text-slate-900" data-summary-key="variable_products">{{ $previewSummary['variable_products'] ?? 0 }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Variant Rows</p>
        <p class="text-lg font-semibold text-slate-900" data-summary-key="variant_rows">{{ $previewSummary['variant_rows'] ?? 0 }}</p>
    </div>
</div>
