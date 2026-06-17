<div class="manual-order-line" data-line-item data-match-status="{{ ($item['source_product_id'] ?? '') !== '' ? 'matched' : 'manual' }}">
    <div class="manual-order-line-top">
        <div class="manual-order-search-wrap">
            <label class="manual-order-label">Product search</label>
            <div class="manual-order-search-row">
                <input type="search" class="form-input w-full" data-product-search placeholder="Search by name, LK model, IBS model, SM model…" autocomplete="off" value="{{ $item['product_name'] ?? '' }}">
                <button type="button" class="btn btn-ghost btn-sm" data-clear-product title="Clear selection">Clear</button>
            </div>
            <div class="manual-order-search-results" data-search-results hidden></div>
        </div>
        <div class="manual-order-line-meta">
            <span class="manual-order-match manual-order-match--{{ ($item['source_product_id'] ?? '') !== '' ? 'matched' : 'manual' }}" data-match-status>{{ ($item['source_product_id'] ?? '') !== '' ? 'Matched' : 'Manual item' }}</span>
            @if ($removable)
                <button type="button" class="btn btn-ghost btn-sm manual-order-remove" data-remove-line title="Remove item">Remove</button>
            @endif
        </div>
    </div>

    <input type="hidden" name="items[{{ $index }}][source_product_id]" value="{{ $item['source_product_id'] ?? '' }}" data-source-product-id>

    <div class="manual-order-line-grid">
        <div class="manual-order-line-span-2">
            <label class="manual-order-label">Product name</label>
            <input type="text" name="items[{{ $index }}][product_name]" value="{{ $item['product_name'] ?? '' }}" class="form-input w-full" data-product-name required>
            @error('items.'.$index.'.product_name')
                <p class="manual-order-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="manual-order-label">Model / SKU</label>
            <input type="text" name="items[{{ $index }}][model]" value="{{ $item['model'] ?? '' }}" class="form-input w-full" data-model>
        </div>
        <div>
            <label class="manual-order-label">Option / variant</label>
            <input type="text" name="items[{{ $index }}][option]" value="{{ $item['option'] ?? '' }}" class="form-input w-full" data-option placeholder="Color: Blue">
        </div>
        <div>
            <label class="manual-order-label">Qty</label>
            <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="form-input w-full" data-qty required>
            @error('items.'.$index.'.quantity')
                <p class="manual-order-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="manual-order-label">Sale price / COD</label>
            <input type="number" name="items[{{ $index }}][sale_price]" value="{{ $item['sale_price'] ?? '' }}" min="0" step="0.01" class="form-input w-full" data-sale-price required>
            @error('items.'.$index.'.sale_price')
                <p class="manual-order-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="manual-order-label">Supplier cost</label>
            <input type="text" class="form-input w-full manual-order-readonly" data-supplier-cost value="{{ $item['supplier_cost'] ?? '' }}" readonly tabindex="-1" placeholder="—">
        </div>
    </div>
</div>
