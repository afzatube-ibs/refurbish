@php
    $defaultItem = [
        'source_product_id' => '',
        'product_name' => '',
        'model' => '',
        'option' => '',
        'quantity' => 1,
        'sale_price' => '',
        'supplier_cost' => '',
        'match_status' => 'manual',
    ];
    $oldItems = old('items', [$defaultItem]);
    $searchUrl = route('order-map.create.products.search');
@endphp
@extends('layouts.app')

@section('title', 'Create Order — DropFlow SFM')
@section('page-title', 'Create Order')
@section('page-subtitle', 'Create inbox, phone, or offline supplier order')

@section('page-actions')
    <a href="{{ route('order-map.index') }}" class="header-action-btn header-action-btn--secondary">← Back to Queue</a>
@endsection

@section('content')
<form method="POST" action="{{ route('order-map.store') }}" class="manual-order-builder" id="manual-order-form" data-product-search-url="{{ $searchUrl }}">
    @csrf

    <section class="manual-order-section order-map-list-card">
        <header class="manual-order-section-head">
            <h2>Order Source</h2>
        </header>
        <div class="manual-order-section-body manual-order-grid-3">
            <div>
                <label for="source_store" class="manual-order-label">Store</label>
                <select name="source_store" id="source_store" class="form-input w-full" required>
                    @foreach ($sourceStores as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_store', 'lokkisona') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source_type" class="manual-order-label">Source type</label>
                <select name="source_type" id="source_type" class="form-input w-full" required>
                    @foreach ($sourceTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_type', 'phone') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('source_type')
                    <p class="manual-order-error">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="reference_note" class="manual-order-label">Reference note <span class="manual-order-optional">optional</span></label>
                <input type="text" name="reference_note" id="reference_note" value="{{ old('reference_note') }}" class="form-input w-full" placeholder="Inbox thread, call ref, etc.">
            </div>
        </div>
    </section>

    <section class="manual-order-section order-map-list-card">
        <header class="manual-order-section-head">
            <h2>Customer Details</h2>
        </header>
        <div class="manual-order-section-body space-y-4">
            <div class="manual-order-grid-2">
                <div>
                    <label for="customer_name" class="manual-order-label">Customer name</label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" class="form-input w-full" required>
                    @error('customer_name')
                        <p class="manual-order-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="customer_phone" class="manual-order-label">Phone</label>
                    <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" class="form-input w-full" required>
                    @error('customer_phone')
                        <p class="manual-order-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div>
                <label for="customer_address" class="manual-order-label">Address</label>
                <textarea name="customer_address" id="customer_address" rows="3" class="form-input w-full" required>{{ old('customer_address') }}</textarea>
                @error('customer_address')
                    <p class="manual-order-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="manual-order-grid-2">
                <div>
                    <label for="city_zone" class="manual-order-label">City / zone <span class="manual-order-optional">optional</span></label>
                    <input type="text" name="city_zone" id="city_zone" value="{{ old('city_zone') }}" class="form-input w-full">
                </div>
                <div>
                    <label for="delivery_note" class="manual-order-label">Delivery note <span class="manual-order-optional">optional</span></label>
                    <input type="text" name="delivery_note" id="delivery_note" value="{{ old('delivery_note') }}" class="form-input w-full" placeholder="Gate code, landmark, etc.">
                </div>
            </div>
        </div>
    </section>

    <section class="manual-order-section order-map-list-card">
        <header class="manual-order-section-head manual-order-section-head--split">
            <h2>Order Items</h2>
            <button type="button" class="btn btn-secondary btn-sm" id="add-line-item">Add Item</button>
        </header>
        <div class="manual-order-section-body" id="line-items-container">
            @foreach ($oldItems as $index => $item)
                @include('order-map.partials.manual-order-line', [
                    'index' => $index,
                    'item' => array_merge($defaultItem, is_array($item) ? $item : []),
                    'removable' => $index > 0,
                ])
            @endforeach
        </div>
        @error('items')
            <p class="manual-order-error manual-order-error--section">{{ $message }}</p>
        @enderror
    </section>

    <section class="manual-order-section order-map-list-card">
        <header class="manual-order-section-head">
            <h2>Totals</h2>
        </header>
        <div class="manual-order-section-body">
            <dl class="manual-order-totals">
                <div><dt>Total Qty</dt><dd id="total-qty">0</dd></div>
                <div><dt>Customer Total / COD</dt><dd id="total-sale">0.00</dd></div>
                <div><dt>Supplier Cost Total</dt><dd id="total-cost">0.00</dd></div>
                <div><dt>Manual items</dt><dd id="total-manual">0</dd></div>
            </dl>
        </div>
    </section>

    <div class="manual-order-actions">
        <a href="{{ route('order-map.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Order</button>
    </div>
</form>

<template id="manual-order-line-template">
    @include('order-map.partials.manual-order-line', [
        'index' => '__INDEX__',
        'item' => $defaultItem,
        'removable' => true,
    ])
</template>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('manual-order-form');
    var container = document.getElementById('line-items-container');
    var template = document.getElementById('manual-order-line-template');
    var addBtn = document.getElementById('add-line-item');
    var searchUrl = form ? form.getAttribute('data-product-search-url') : '';
    var nextIndex = container ? container.children.length : 0;
    var searchTimers = new WeakMap();

    function money(value) {
        var num = parseFloat(value);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseMoney(value) {
        var num = parseFloat(String(value || '').replace(/,/g, ''));
        return isNaN(num) ? 0 : num;
    }

    function lineRoot(el) {
        return el.closest('[data-line-item]');
    }

    function setMatchStatus(line, status, supplierCost) {
        var badge = line.querySelector('[data-match-status]');
        var costInput = line.querySelector('[data-supplier-cost]');
        if (!badge) return;
        badge.textContent = status === 'matched' ? 'Matched' : 'Manual item';
        badge.classList.toggle('manual-order-match--matched', status === 'matched');
        badge.classList.toggle('manual-order-match--manual', status !== 'matched');
        if (costInput) {
            costInput.value = supplierCost != null && supplierCost !== '' ? supplierCost : '';
        }
        line.dataset.matchStatus = status;
        recalcTotals();
    }

    function recalcTotals() {
        var totalQty = 0;
        var totalSale = 0;
        var totalCost = 0;
        var manualCount = 0;

        container.querySelectorAll('[data-line-item]').forEach(function (line) {
            var qty = parseInt(line.querySelector('[data-qty]')?.value || '0', 10) || 0;
            var sale = parseMoney(line.querySelector('[data-sale-price]')?.value);
            var cost = parseMoney(line.querySelector('[data-supplier-cost]')?.value);
            var matched = line.dataset.matchStatus === 'matched';
            totalQty += qty;
            totalSale += qty * sale;
            if (matched && cost > 0) totalCost += qty * cost;
            if (!matched) manualCount += 1;
        });

        var qtyEl = document.getElementById('total-qty');
        var saleEl = document.getElementById('total-sale');
        var costEl = document.getElementById('total-cost');
        var manualEl = document.getElementById('total-manual');
        if (qtyEl) qtyEl.textContent = String(totalQty);
        if (saleEl) saleEl.textContent = money(totalSale);
        if (costEl) costEl.textContent = money(totalCost);
        if (manualEl) manualEl.textContent = String(manualCount);
    }

    function hideResults(line) {
        var box = line.querySelector('[data-search-results]');
        if (box) {
            box.hidden = true;
            box.innerHTML = '';
        }
    }

    function showResults(line, results) {
        var box = line.querySelector('[data-search-results]');
        if (!box) return;
        box.innerHTML = '';
        if (!results.length) {
            box.innerHTML = '<p class="manual-order-search-empty">No products found — enter item manually.</p>';
            box.hidden = false;
            return;
        }
        results.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'manual-order-search-hit';
            btn.innerHTML = '<strong>' + escapeHtml(item.product_name) + '</strong><span>' +
                escapeHtml([item.model, item.ibs_model, item.source_product_id].filter(Boolean).join(' · ')) + '</span>';
            btn.addEventListener('click', function () {
                applyProduct(line, item);
                hideResults(line);
            });
            box.appendChild(btn);
        });
        box.hidden = false;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function applyProduct(line, item) {
        line.querySelector('[data-source-product-id]').value = item.source_product_id || '';
        line.querySelector('[data-product-name]').value = item.product_name || '';
        line.querySelector('[data-model]').value = item.model || '';
        var searchInput = line.querySelector('[data-product-search]');
        if (searchInput) searchInput.value = item.product_name || '';
        setMatchStatus(line, 'matched', item.supplier_cost);
        recalcTotals();
    }

    function searchProducts(line, query) {
        if (!searchUrl || query.length < 2) {
            hideResults(line);
            return;
        }
        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) { showResults(line, data.results || []); })
            .catch(function () { hideResults(line); });
    }

    function bindLine(line) {
        if (line.dataset.bound) return;
        line.dataset.bound = '1';

        var searchInput = line.querySelector('[data-product-search]');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var existing = searchTimers.get(line);
                if (existing) clearTimeout(existing);
                searchTimers.set(line, setTimeout(function () {
                    searchProducts(line, searchInput.value.trim());
                }, 250));
            });
            searchInput.addEventListener('focus', function () {
                if (searchInput.value.trim().length >= 2) {
                    searchProducts(line, searchInput.value.trim());
                }
            });
        }

        ['[data-product-name]', '[data-model]', '[data-option]', '[data-qty]', '[data-sale-price]'].forEach(function (sel) {
            line.querySelector(sel)?.addEventListener('input', function () {
                if (sel !== '[data-qty]' && sel !== '[data-sale-price]') {
                    var idField = line.querySelector('[data-source-product-id]');
                    if (idField && idField.value === '' && line.dataset.matchStatus !== 'matched') {
                        setMatchStatus(line, 'manual', '');
                    } else if (idField && idField.value === '') {
                        setMatchStatus(line, 'manual', '');
                    }
                }
                recalcTotals();
            });
        });

        line.querySelector('[data-clear-product]')?.addEventListener('click', function () {
            line.querySelector('[data-source-product-id]').value = '';
            if (searchInput) searchInput.value = '';
            setMatchStatus(line, 'manual', '');
            hideResults(line);
        });

        line.querySelector('[data-remove-line]')?.addEventListener('click', function () {
            line.remove();
            recalcTotals();
        });

        document.addEventListener('click', function (e) {
            if (!line.contains(e.target)) hideResults(line);
        });

        if (line.querySelector('[data-source-product-id]')?.value) {
            setMatchStatus(line, 'matched', line.querySelector('[data-supplier-cost]')?.value || '');
        } else {
            setMatchStatus(line, 'manual', '');
        }
    }

    function bindAllLines() {
        container.querySelectorAll('[data-line-item]').forEach(bindLine);
        recalcTotals();
    }

    addBtn?.addEventListener('click', function () {
        if (!template || !container) return;
        var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var line = wrapper.firstElementChild;
        container.appendChild(line);
        bindLine(line);
        recalcTotals();
    });

    bindAllLines();
})();
</script>
@endpush
@endsection
