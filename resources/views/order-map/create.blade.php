@extends('layouts.app')

@section('title', 'Create Manual Order — DropFlow SFM')
@section('page-title', 'Create Manual Order')
@section('page-subtitle', 'Enter customer details and line items — order ID will use MAN- prefix')

@section('page-actions')
    <a href="{{ route('order-map.index') }}" class="header-action-btn header-action-btn--secondary">← Back to Queue</a>
@endsection

@section('content')
<form method="POST" action="{{ route('order-map.store') }}" class="order-map-manual-form space-y-6" id="manual-order-form">
    @csrf

    <div class="order-map-list-card p-6 space-y-4">
        <h2 class="font-medium text-slate-900">Customer</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="customer_name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" class="form-input w-full" required>
                @error('customer_name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="customer_phone" class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" class="form-input w-full" required>
                @error('customer_phone')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="customer_address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
            <textarea name="customer_address" id="customer_address" rows="3" class="form-input w-full" required>{{ old('customer_address') }}</textarea>
            @error('customer_address')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="order-map-list-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
            <h2 class="font-medium text-slate-900">Line Items</h2>
            <button type="button" class="btn btn-secondary btn-sm" id="add-line-item">Add Line</button>
        </div>

        <div class="p-6 space-y-4" id="line-items-container">
            @php $oldItems = old('items', [['source_product_id' => '', 'product_name' => '', 'model' => '', 'quantity' => 1, 'sale_price' => '']]); @endphp
            @foreach ($oldItems as $index => $item)
                @include('order-map.partials.manual-order-line', ['index' => $index, 'item' => $item, 'removable' => $index > 0])
            @endforeach
        </div>

        @error('items')
            <p class="px-6 pb-4 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('order-map.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Order</button>
    </div>
</form>

<template id="manual-order-line-template">
    @include('order-map.partials.manual-order-line', ['index' => '__INDEX__', 'item' => ['source_product_id' => '', 'product_name' => '', 'model' => '', 'quantity' => 1, 'sale_price' => ''], 'removable' => true])
</template>

@push('scripts')
<script>
(function () {
    var container = document.getElementById('line-items-container');
    var template = document.getElementById('manual-order-line-template');
    var addBtn = document.getElementById('add-line-item');
    var nextIndex = container ? container.children.length : 0;

    function bindRemoveButtons() {
        container.querySelectorAll('[data-remove-line]').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                btn.closest('[data-line-item]')?.remove();
            });
        });
    }

    addBtn?.addEventListener('click', function () {
        if (!template || !container) return;
        var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        container.appendChild(wrapper.firstElementChild);
        bindRemoveButtons();
    });

    bindRemoveButtons();
})();
</script>
@endpush
@endsection
