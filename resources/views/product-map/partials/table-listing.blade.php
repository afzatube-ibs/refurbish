@php
    $formatRate = static function ($rate): string {
        return $rate !== null && $rate !== '' ? number_format((float) $rate, 2) : '—';
    };
    $displayField = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    };
    $displayStock = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) (int) $value;
    };
    $displayIbsStock = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) (int) $value;
    };
    $rows = $listingProducts ?? $products ?? [];
    $previewLoaded = ! empty($products ?? []);
    $showPagination = $previewLoaded;
@endphp

<div class="product-map-list-card">
    @if ($showPagination)
        @include('product-map.partials.list-pagination', ['placement' => 'top'])
    @endif

<div class="product-map-table-scroll">
<div class="product-map-list">
    <div class="product-map-list-header">
        <table class="min-w-full text-sm table-compact product-map-table product-map-table-layout">
            @include('product-map.partials.table-colgroup')
            <thead class="bg-slate-50">
                <tr>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">LK Product ID</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Main Image</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">LK Model</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">IBS Model</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">SM Model</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap" title="IBS supplier cost (local)">Rate</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Stock</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">IBS Stock</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Product Type</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Category</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Health</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Actions</th>
                </tr>
            </thead>
        </table>
    </div>

    @forelse ($rows as $index => $product)
        @php
            $health = $product['health'] ?? ['status' => 'ok', 'label' => 'OK', 'issues' => []];
            $options = $product['options'] ?? $product['variants'] ?? [];
            $rowId = 'product-row-'.$index;
            $isVariable = count($options) > 0;
            $lkModel = $product['lk_model'] ?? $product['model'] ?? null;
            $ibsModel = $product['ibs_model'] ?? null;
            $smModel = $product['sm_model'] ?? null;
            $rate = $product['rate'] ?? null;
            $stock = $product['stock'] ?? null;
            $ibsStock = $product['ibs_stock'] ?? null;
            $parentImage = $product['image'] ?? $product['main_image'] ?? null;
            $productId = $product['oc_product_id'] ?? $product['product_id'] ?? null;
            $productCategory = $product['product_category'] ?? null;
            $healthIssues = $health['issues'] ?? [];
            $healthTitle = $healthIssues !== [] ? implode('; ', $healthIssues) : 'No issues';
        @endphp
        <div class="product-map-group" id="group-{{ $rowId }}">
            <div>
                <table class="min-w-full text-sm table-compact product-map-table product-map-table-layout">
                    @include('product-map.partials.table-colgroup')
                    <tbody>
                        <tr id="{{ $rowId }}" class="product-map-parent-row hover:bg-slate-50 product-map-row-open cursor-pointer" data-product-index="{{ $index }}" data-control-open-row>
                            <td class="col-center font-mono text-xs text-slate-700 whitespace-nowrap">
                                {{ $displayField($productId) }}
                            </td>
                            <td class="col-center">
                                @if (! empty($parentImage))
                                    <img src="{{ $parentImage }}" alt="" class="product-thumb" loading="lazy">
                                @else
                                    <span class="inline-flex product-thumb items-center justify-center text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="col-center font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                                {{ $displayField($lkModel) }}
                            </td>
                            <td class="col-center font-mono text-xs text-slate-700 whitespace-nowrap" data-cell="ibs_model">
                                {{ $displayField($ibsModel) }}
                            </td>
                            <td class="col-center text-slate-400 whitespace-nowrap" data-cell="sm_model">{{ $displayField($smModel) }}</td>
                            <td class="col-center text-slate-800 whitespace-nowrap pm-num-cell" data-cell="rate">{{ $formatRate($rate) }}</td>
                            <td class="col-center font-medium whitespace-nowrap pm-num-cell {{ is_numeric($stock) && (int) $stock < 0 ? 'stock-negative' : 'text-slate-900' }}">
                                {{ $displayStock($stock) }}
                            </td>
                            <td class="col-center text-slate-400 whitespace-nowrap pm-num-cell" data-cell="ibs_stock">{{ $displayIbsStock($ibsStock) }}</td>
                            <td class="col-center whitespace-nowrap">
                                @if ($isVariable)
                                    <button type="button"
                                            class="variants-btn expand-toggle"
                                            data-target="{{ $rowId }}"
                                            data-count="{{ count($options) }}"
                                            aria-expanded="false"
                                            aria-controls="{{ $rowId }}-variants">
                                        Variable ({{ count($options) }})
                                    </button>
                                @else
                                    <span class="product-type-simple">Simple</span>
                                @endif
                            </td>
                            <td class="col-center text-slate-700 whitespace-nowrap" data-cell="product_category">
                                {{ $displayField($productCategory) }}
                            </td>
                            <td class="col-center" data-cell="health">
                                @include('product-map.partials.health-badge', ['health' => $health, 'healthTitle' => $healthTitle, 'healthIssues' => $healthIssues])
                            </td>
                            <td class="col-center whitespace-nowrap">
                                <button type="button"
                                        class="product-control-edit-btn"
                                        data-control-open
                                        data-product-index="{{ $index }}"
                                        data-product-id="{{ $productId }}"
                                        title="Edit IBS rate, stock, models, and category">
                                    Edit fields
                                </button>
                            </td>
                        </tr>
                        @if ($isVariable)
                            <tr class="product-map-variant-caption hidden" data-parent-row="{{ $rowId }}">
                                <td colspan="12">Variants of {{ $displayField($lkModel) }}</td>
                            </tr>
                            @foreach ($options as $optionIndex => $option)
                                @php
                                    $optionHealth = $option['health'] ?? ['status' => 'ok', 'label' => 'OK', 'issues' => []];
                                    $optionIssues = $optionHealth['issues'] ?? [];
                                    $optionHealthTitle = $optionIssues !== [] ? implode('; ', $optionIssues) : 'No issues';
                                    $optionLkModel = $option['lk_model'] ?? $option['model'] ?? null;
                                    $optionIbsModel = $option['ibs_model'] ?? null;
                                    $optionSmModel = $option['sm_model'] ?? null;
                                    $optionIbsStock = $option['ibs_stock'] ?? null;
                                    $optionStock = $option['stock'] ?? $option['quantity'] ?? null;
                                    $optionImage = $option['image'] ?? $option['option_image'] ?? null;
                                @endphp
                                <tr class="product-map-variant-row hidden"
                                    data-parent-row="{{ $rowId }}"
                                    id="{{ $rowId }}-variant-{{ $optionIndex }}">
                                    <td class="col-center text-slate-400 whitespace-nowrap">—</td>
                                    <td class="col-center">
                                        @if (! empty($optionImage))
                                            <img src="{{ $optionImage }}" alt="" class="product-thumb" loading="lazy">
                                        @else
                                            <span class="inline-flex product-thumb items-center justify-center text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="col-center font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                                        {{ $displayField($optionLkModel) }}
                                    </td>
                                    <td class="col-center font-mono text-xs text-slate-700 whitespace-nowrap" data-cell="ibs_model">
                                        {{ $displayField($optionIbsModel) }}
                                    </td>
                                    <td class="col-center text-slate-400 whitespace-nowrap" data-cell="sm_model">{{ $displayField($optionSmModel) }}</td>
                                    <td class="col-center text-slate-400 whitespace-nowrap" data-cell="rate">—</td>
                                    <td class="col-center font-medium whitespace-nowrap {{ is_numeric($optionStock) && (int) $optionStock < 0 ? 'stock-negative' : 'text-slate-900' }}">
                                        {{ $displayStock($optionStock) }}
                                    </td>
                                    <td class="col-center text-slate-400 whitespace-nowrap" data-cell="ibs_stock">{{ $displayIbsStock($optionIbsStock) }}</td>
                                    <td class="col-center text-slate-400 whitespace-nowrap">—</td>
                                    <td class="col-center text-slate-400 whitespace-nowrap">—</td>
                                    <td class="col-center" data-cell="health">
                                        @include('product-map.partials.health-badge', [
                                            'health' => $optionHealth,
                                            'healthTitle' => $optionHealthTitle,
                                            'healthIssues' => $optionIssues,
                                        ])
                                    </td>
                                    <td class="col-center text-slate-400 whitespace-nowrap">—</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="product-map-group">
            <div class="pm-empty-state">
                <div class="pm-empty-state-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                @if ($previewLoaded)
                    <p class="pm-empty-state-title">No products match your filters</p>
                    <p class="pm-empty-state-text">Try adjusting search or filter options, or <a href="{{ route('product-map.index') }}" class="text-slate-700 underline">clear filters</a>.</p>
                @else
                    <p class="pm-empty-state-title">No LK products saved yet</p>
                    <p class="pm-empty-state-text">Click <strong>Sync LK Products</strong> to fetch a Lokkisona snapshot and save it into DropFlow. Supplier fields are edited from each row after sync.</p>
                @endif
            </div>
        </div>
    @endforelse
</div>
</div>

    @if ($showPagination)
        @include('product-map.partials.list-pagination', ['placement' => 'footer'])
    @endif
</div>

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.expand-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var parentKey = button.getAttribute('data-target');
            var parentRow = document.getElementById(parentKey);
            var variantRows = document.querySelectorAll('tr.product-map-variant-row[data-parent-row="' + parentKey + '"]');
            var captionRow = document.querySelector('tr.product-map-variant-caption[data-parent-row="' + parentKey + '"]');
            if (!variantRows.length) return;

            var isHidden = variantRows[0].classList.contains('hidden');
            var count = button.getAttribute('data-count') || '';

            variantRows.forEach(function (row) {
                row.classList.toggle('hidden', !isHidden);
            });

            if (captionRow) {
                captionRow.classList.toggle('hidden', !isHidden);
            }

            var group = parentRow ? parentRow.closest('.product-map-group') : null;
            if (group) {
                group.classList.toggle('is-expanded', isHidden);
            }

            button.classList.toggle('is-open', isHidden);
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            button.textContent = isHidden ? 'Hide (' + count + ')' : 'Variable (' + count + ')';
        });
    });
})();
</script>
@endpush
