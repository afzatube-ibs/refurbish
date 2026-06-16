<div class="order-map-manual-line border border-slate-200 rounded-md p-4" data-line-item>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Product ID</label>
            <input type="text" name="items[{{ $index }}][source_product_id]" value="{{ $item['source_product_id'] ?? '' }}" class="form-input w-full" required>
            @error('items.'.$index.'.source_product_id')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Product Name</label>
            <input type="text" name="items[{{ $index }}][product_name]" value="{{ $item['product_name'] ?? '' }}" class="form-input w-full" required>
            @error('items.'.$index.'.product_name')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Model / Variant</label>
            <input type="text" name="items[{{ $index }}][model]" value="{{ $item['model'] ?? '' }}" class="form-input w-full">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Qty</label>
            <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="form-input w-full" required>
            @error('items.'.$index.'.quantity')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Sale Price</label>
            <input type="number" name="items[{{ $index }}][sale_price]" value="{{ $item['sale_price'] ?? '' }}" min="0" step="0.01" class="form-input w-full">
        </div>
        <div class="md:col-span-1 flex justify-end">
            @if ($removable)
                <button type="button" class="btn btn-ghost btn-sm text-red-700" data-remove-line title="Remove line">×</button>
            @endif
        </div>
    </div>
</div>
