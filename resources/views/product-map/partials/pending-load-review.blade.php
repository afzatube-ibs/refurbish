@php
    $pendingProducts = is_array($pendingProducts ?? null) ? $pendingProducts : [];
    $pendingCount = (int) ($pendingCount ?? count($pendingProducts));
    $isFirstLoad = (bool) ($isFirstLoad ?? false);
    $displayField = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    };
@endphp

<div class="pm-pending-review-card product-map-list-card">
    <div class="pm-pending-review-header">
        <div>
            <h2 class="pm-pending-review-title">Review before adding</h2>
            @if ($isFirstLoad)
                <p class="pm-pending-review-subtitle">
                    {{ $pendingCount === 1 ? '1 product fetched' : $pendingCount.' products fetched' }} from OpenCart.
                    Confirm below to add {{ $pendingCount === 1 ? 'it' : 'them' }} to your Product Map.
                </p>
            @else
                <p class="pm-pending-review-subtitle">
                    {{ $pendingCount === 1 ? '1 new product found' : $pendingCount.' new products found' }}.
                    Review below before adding to your Product Map. Existing products are unchanged.
                </p>
            @endif
        </div>
        <div class="pm-pending-review-actions">
            <form method="POST" action="{{ route('product-map.load.confirm') }}">
                @csrf
                <button type="submit" class="header-action-btn header-action-btn--primary">
                    Add All New
                </button>
            </form>
            <form method="POST" action="{{ route('product-map.load.cancel') }}">
                @csrf
                <button type="submit" class="header-action-btn header-action-btn--secondary">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <div class="pm-pending-review-meta">
        Showing {{ $pendingCount }} {{ $pendingCount === 1 ? 'product' : 'products' }} · Status: New
    </div>

    <div class="pm-pending-review-table-wrap overflow-x-auto">
        <table class="min-w-full text-sm table-compact product-map-table pm-pending-review-table">
            <thead class="bg-slate-50">
                <tr>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">OC Product ID</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Image</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">OC Model</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Type</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pendingProducts as $product)
                    @php
                        $options = $product['options'] ?? $product['variants'] ?? [];
                        $isVariable = ($product['type'] ?? '') === 'variable' || count($options) > 0;
                        $productId = $product['oc_product_id'] ?? $product['product_id'] ?? null;
                        $ocModel = $product['lk_model'] ?? $product['model'] ?? null;
                        $image = $product['image'] ?? $product['main_image'] ?? null;
                        $variantCount = count($options);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="col-center font-mono text-xs text-slate-700 whitespace-nowrap">
                            {{ $displayField($productId) }}
                        </td>
                        <td class="col-center">
                            @if (! empty($image))
                                <img src="{{ $image }}" alt="" class="product-thumb" loading="lazy">
                            @else
                                <span class="inline-flex product-thumb items-center justify-center text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="col-center font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                            {{ $displayField($ocModel) }}
                        </td>
                        <td class="col-center whitespace-nowrap">
                            @if ($isVariable)
                                <span class="product-type-variable">Variable{{ $variantCount > 0 ? ' ('.$variantCount.')' : '' }}</span>
                            @else
                                <span class="product-type-simple">Simple</span>
                            @endif
                        </td>
                        <td class="col-center whitespace-nowrap">
                            <span class="pm-status-new">New</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="pm-pending-review-footer">
        <form method="POST" action="{{ route('product-map.load.confirm') }}">
            @csrf
            <button type="submit" class="header-action-btn header-action-btn--primary">
                Add All New
            </button>
        </form>
        <form method="POST" action="{{ route('product-map.load.cancel') }}">
            @csrf
            <button type="submit" class="header-action-btn header-action-btn--secondary">
                Cancel
            </button>
        </form>
    </div>
</div>
