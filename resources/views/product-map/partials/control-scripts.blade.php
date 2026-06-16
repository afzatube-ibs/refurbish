@push('scripts')
<script>
(function () {
    'use strict';

    var products = @json($products ?? []);
    var stockReasons = @json($stockReasons ?? []);
    var categoryOptions = @json($productCategories ?? []);
    var saveUrl = @json(route('product-map.control.save'));
    var historyUrl = @json(route('product-map.control.history'));
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    var modal = document.getElementById('productControlCenterModal');
    var form = document.getElementById('productControlCenterForm');
    if (!modal || !form) return;

    var variantLinesBody = document.getElementById('pccVariantLinesBody');
    var simpleLinesBody = document.getElementById('pccSimpleLinesBody');
    var variantSection = document.getElementById('pccVariantSection');
    var simpleTableSection = document.getElementById('pccSimpleTableSection');
    var vendorMappingCard = document.getElementById('pccVendorMappingCard');
    var workspaceEl = document.getElementById('pccWorkspace');
    var saveBtn = document.getElementById('pccSaveBtn');
    var cancelBtn = document.getElementById('pccCancelBtn');
    var modalErrorEl = document.getElementById('pccModalError');
    var adjustPopover = document.getElementById('pccAdjustPopover');

    var currentIndex = null;
    var workingProduct = null;
    var baselineProduct = null;
    var pendingChanges = [];
    var historyCounts = {};
    var historyCache = {};
    var isDirty = false;
    var adjustContext = null;

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function trimVal(value) {
        return value == null ? '' : String(value).trim();
    }

    function variantOptionLabel(opt, index) {
        var value = trimVal(opt.option_value);
        if (value !== '' && value !== '—') return value;
        var label = trimVal(opt.option_label);
        if (label !== '') return label;
        return 'Variant ' + (index + 1);
    }

    function displayVal(value) {
        return trimVal(value) === '' ? 'Not set' : String(value);
    }

    function effectiveLowWarning(scope, index) {
        if (!workingProduct) return 5;
        if (scope === 'variant') {
            var opt = (workingProduct.options || workingProduct.variants || [])[index];
            if (opt && opt.low_warning !== null && opt.low_warning !== undefined && opt.low_warning !== '') {
                return parseInt(opt.low_warning, 10);
            }
        }
        var parent = workingProduct.low_warning;
        if (parent !== null && parent !== undefined && parent !== '') {
            return parseInt(parent, 10);
        }
        return 5;
    }

    function displayLowWarning(scope, index, stored) {
        if (scope === 'variant') {
            return String(effectiveLowWarning(scope, index));
        }
        return displayLow(stored);
    }

    function displayLow(value) {
        return value === null || value === undefined || value === '' ? '' : String(parseInt(value, 10));
    }

    function formatRate(value) {
        if (value === null || value === undefined || value === '') return 'Not set';
        return Number(value).toFixed(2);
    }

    function formatStock(value) {
        if (value === null || value === undefined || value === '') return 'Not set';
        return String(parseInt(value, 10));
    }

    function numericStockValue(value) {
        if (value === null || value === undefined || value === '') return 0;
        return parseInt(value, 10);
    }

    function formatAdjustCurrentValue(mode, value) {
        if (mode === 'price') {
            return formatRate(value);
        }
        return String(numericStockValue(value));
    }

    function effectiveVariantRate(override, parentRate) {
        if (override !== null && override !== undefined && override !== '') {
            return Number(override);
        }
        return parentRate === null || parentRate === undefined || parentRate === '' ? null : Number(parentRate);
    }

    function normalizeWorkingProduct(p) {
        var copy = cloneProduct(p);
        (copy.options || copy.variants || []).forEach(function (opt) {
            if (opt.rate_override === undefined) {
                opt.rate_override = opt.rate != null && copy.rate != null && Number(opt.rate) === Number(copy.rate)
                    ? null
                    : (opt.rate != null ? Number(opt.rate) : null);
            }
            opt.rate = effectiveVariantRate(opt.rate_override, copy.rate);
        });
        return copy;
    }

    function effectiveRate(scope, index) {
        if (!workingProduct) return null;
        if (scope === 'parent' || scope === 'simple') return workingProduct.rate;
        var opt = (workingProduct.options || workingProduct.variants || [])[index];
        if (!opt) return null;
        return effectiveVariantRate(opt.rate_override, workingProduct.rate);
    }

    function storedValue(scope, index, field) {
        if (!workingProduct) return null;
        if (field === 'rate' && scope === 'variant') {
            var opt = (workingProduct.options || workingProduct.variants || [])[index];
            return opt ? opt.rate_override : null;
        }
        if (scope === 'parent' || scope === 'simple') return workingProduct[field];
        var option = (workingProduct.options || workingProduct.variants || [])[index];
        return option ? option[field] : null;
    }

    function displayRate(scope, index) {
        var value = scope === 'variant' ? effectiveRate(scope, index) : storedValue(scope, index, 'rate');
        return formatRate(value);
    }

    function ensureCategoryOption(name) {
        var value = trimVal(name);
        if (value !== '' && categoryOptions.indexOf(value) === -1) {
            categoryOptions.push(value);
            categoryOptions.sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); });
        }
        return value;
    }

    function chipHtml(value) {
        if (trimVal(value) === '') {
            return '<span class="pcc-field-chip pcc-field-chip-empty">Not set</span>';
        }
        return '<span class="pcc-field-chip">' + esc(value) + '</span>';
    }

    function cloneProduct(p) {
        return JSON.parse(JSON.stringify(p));
    }

    function isVariable(p) {
        return ((p.options || p.variants || []).length > 0);
    }

    function productId(p) {
        return String(p.product_id || p.oc_product_id || '');
    }

    function imageCellHtml(url) {
        if (trimVal(url) !== '') {
            return '<div class="pcc-thumb-44"><img src="' + esc(url) + '" alt="" loading="lazy"></div>';
        }
        return '<div class="pcc-thumb-44 pcc-thumb-44-empty"><span>No image</span></div>';
    }

    function setImage(url) {
        var img = document.getElementById('pccProductImage');
        var placeholder = document.getElementById('pccImagePlaceholder');
        if (!img || !placeholder) return;
        if (trimVal(url) !== '') {
            placeholder.hidden = true;
            img.hidden = false;
            img.onerror = function () {
                img.hidden = true;
                img.removeAttribute('src');
                placeholder.hidden = false;
            };
            img.src = url;
        } else {
            img.hidden = true;
            img.removeAttribute('src');
            placeholder.hidden = false;
        }
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = trimVal(value) === '' ? '—' : String(value);
    }

    function showModalError(message) {
        if (!modalErrorEl) return;
        modalErrorEl.textContent = message || '';
        modalErrorEl.hidden = !message;
    }

    function setDirty(dirty) {
        isDirty = !!dirty;
        if (saveBtn) {
            saveBtn.disabled = !isDirty;
            saveBtn.classList.toggle('pcc-save-dirty', isDirty);
        }
        if (cancelBtn) cancelBtn.hidden = !isDirty;
        form.classList.toggle('pcc-form-dirty', isDirty);
    }

    function markDirty() {
        setDirty(true);
    }

    function markRowDirty(el) {
        if (el) el.classList.add('pcc-row-dirty');
    }

    function clearDirtyHighlights() {
        document.querySelectorAll('.pcc-row-dirty').forEach(function (el) {
            el.classList.remove('pcc-row-dirty');
        });
    }

    function toggleVariableLayout(isVariable) {
        if (variantSection) variantSection.hidden = !isVariable;
        if (simpleTableSection) simpleTableSection.hidden = isVariable;
        if (vendorMappingCard) vendorMappingCard.hidden = !isVariable;
        if (workspaceEl) {
            workspaceEl.classList.toggle('is-variable', isVariable);
            workspaceEl.classList.toggle('is-simple', !isVariable);
        }
    }

    function syncSupplierFieldDisplay(field, value) {
        var display = document.querySelector('.pcc-dblclick-edit[data-field="' + field + '"][data-scope="parent"]');
        if (!display) return;
        if (field === 'low_warning') display.textContent = displayLow(value);
        else if (field === 'rate') display.textContent = formatRate(value);
        else if (field === 'product_category') display.textContent = trimVal(value) === '' ? 'Not set' : String(value);
        else display.textContent = displayVal(value);
    }

    function getWorkingValue(scope, index, field) {
        return storedValue(scope, index, field);
    }

    function applyChangeToWorking(change) {
        if (!workingProduct) return;
        var val = change.computedValue !== undefined ? change.computedValue : change.value;
        if (change.scope === 'parent' || change.scope === 'simple') {
            if (change.field === 'low_warning' && (val === '' || val === null)) workingProduct.low_warning = null;
            else if (change.field === 'product_category') workingProduct.product_category = (val === '' || val === null) ? null : val;
            else workingProduct[change.field] = val;
            if (change.field === 'rate') {
                workingProduct.supplier_cost = val;
                (workingProduct.options || workingProduct.variants || []).forEach(function (opt) {
                    opt.rate = effectiveVariantRate(opt.rate_override, workingProduct.rate);
                });
            }
            return;
        }
        if (change.scope === 'variant') {
            var opt = (workingProduct.options || workingProduct.variants || [])[change.index];
            if (!opt) return;
            if (change.field === 'low_warning' && (val === '' || val === null)) opt.low_warning = null;
            else if (change.field === 'rate') {
                opt.rate_override = (val === '' || val === null) ? null : val;
                opt.rate = effectiveVariantRate(opt.rate_override, workingProduct.rate);
            } else opt[change.field] = val;
        }
    }

    function queueChange(change) {
        pendingChanges.push(change);
        applyChangeToWorking(change);
        markDirty();
        showModalError('');
    }

    function buildInlineSetChange(scope, index, field, rawValue, inputType) {
        var change = { scope: scope, field: field, mode: 'set' };
        if (scope === 'variant') change.index = index;
        if (field === 'low_warning') {
            change.value = rawValue === '' ? null : parseInt(rawValue, 10);
            change.computedValue = change.value;
        } else if (field === 'product_category') {
            change.value = trimVal(String(rawValue)) === '' ? null : String(rawValue);
            change.computedValue = change.value;
        } else if (inputType === 'integer' || field === 'ibs_stock') {
            change.value = rawValue === '' ? null : parseInt(rawValue, 10);
            change.computedValue = change.value;
        } else if (inputType === 'decimal' || field === 'rate') {
            change.value = rawValue === '' ? null : Number(rawValue);
            change.computedValue = change.value;
        } else {
            change.value = rawValue;
            change.computedValue = rawValue;
        }
        return change;
    }

    function stockValueChanged(oldVal, newVal) {
        var oldN = oldVal === null || oldVal === undefined || oldVal === '' ? null : Number(oldVal);
        var newN = newVal === null || newVal === undefined || newVal === '' ? null : Number(newVal);
        if (oldN === null && newN === null) return false;
        if (oldN === null && newN === 0) return false;
        return oldN !== newN;
    }

    function hasIbsStockValue(val) {
        return val !== null && val !== undefined && val !== '';
    }

    function editableCellHtml(scope, field, value, index, inputType) {
        var display;
        if (field === 'rate' && scope === 'variant') {
            display = displayRate(scope, index);
        } else if (field === 'rate') display = formatRate(value);
        else if (field === 'ibs_stock') display = formatStock(value);
        else if (field === 'low_warning') display = displayLowWarning(scope, index, value);
        else display = displayVal(value);
        var inner = trimVal(value) === '' && field !== 'low_warning'
            ? chipHtml('')
            : esc(display === '' ? '' : display);
        if (field === 'low_warning') inner = esc(display);
        return '<span class="pcc-cell-value" data-editable="' + esc(field) + '" data-scope="' + esc(scope) + '" data-index="' + (index == null ? '' : index) + '" data-input-type="' + esc(inputType || 'text') + '" tabindex="0" title="Double-click to edit">' + inner + '</span>';
    }

    function editableCellWithAdjustHtml(scope, field, value, index, adjustMode, inputType) {
        var label = adjustMode === 'price' ? 'Rate' : 'Qty';
        return '<div class="pcc-cell-with-adjust">' +
            editableCellHtml(scope, field, value, index, inputType) +
            '<button type="button" class="pcc-v202-adjust-mini pcc-v202-adjust-trigger" data-adjust-scope="' + esc(scope) + '" data-adjust-mode="' + esc(adjustMode) + '" data-adjust-index="' + (index == null ? '' : index) + '" title="Adjust" aria-label="Adjust">' + esc(label) + '</button>' +
            '</div>';
    }

    function renderVariantLinesTable(p) {
        if (!variantLinesBody) return;
        var options = p.options || p.variants || [];
        variantLinesBody.innerHTML = options.map(function (opt, index) {
            var effective = effectiveRate('variant', index);
            return '<tr data-variant-index="' + index + '">' +
                '<td class="pcc-vcol-option"><span class="pcc-cell-ellipsis">' + esc(variantOptionLabel(opt, index)) + '</span></td>' +
                '<td class="pcc-vcol-image">' + imageCellHtml(opt.image) + '</td>' +
                '<td class="pcc-vcol-model"><span class="pcc-cell-ellipsis">' + esc(opt.lk_model || opt.model || '—') + '</span></td>' +
                '<td class="pcc-vcol-vendor">' + editableCellHtml('variant', 'ibs_model', opt.ibs_model, index, 'text') + '</td>' +
                '<td class="pcc-vcol-sm">' + editableCellHtml('variant', 'sm_model', opt.sm_model, index, 'text') + '</td>' +
                '<td class="pcc-vcol-cost pcc-num pcc-editable-cell">' + editableCellWithAdjustHtml('variant', 'rate', effective, index, 'price', 'decimal') + '</td>' +
                '<td class="pcc-vcol-vstock pcc-num pcc-editable-cell">' + editableCellWithAdjustHtml('variant', 'ibs_stock', opt.ibs_stock, index, 'quantity', 'integer') + '</td>' +
                '<td class="pcc-vcol-warn">' + editableCellHtml('variant', 'low_warning', opt.low_warning, index, 'integer') + '</td>' +
                '</tr>';
        }).join('');
        bindCellEvents(variantLinesBody);
        bindAdjustTriggers(variantLinesBody);
    }

    function renderSimpleLinesTable(p) {
        if (!simpleLinesBody) return;
        simpleLinesBody.innerHTML = '<tr data-simple-row="1">' +
            '<td class="pcc-vcol-image">' + imageCellHtml(p.image) + '</td>' +
            '<td class="pcc-vcol-model"><span class="pcc-cell-ellipsis">' + esc(p.lk_model || p.model || '—') + '</span></td>' +
            '<td class="pcc-vcol-vendor">' + editableCellHtml('simple', 'ibs_model', p.ibs_model, null, 'text') + '</td>' +
            '<td class="pcc-vcol-sm">' + editableCellHtml('simple', 'sm_model', p.sm_model, null, 'text') + '</td>' +
            '<td class="pcc-vcol-category">' + editableCellHtml('simple', 'product_category', p.product_category, null, 'category') + '</td>' +
            '<td class="pcc-vcol-cost pcc-num pcc-editable-cell">' + editableCellWithAdjustHtml('simple', 'rate', p.rate, null, 'price', 'decimal') + '</td>' +
            '<td class="pcc-vcol-vstock pcc-num pcc-editable-cell">' + editableCellWithAdjustHtml('simple', 'ibs_stock', p.ibs_stock, null, 'quantity', 'integer') + '</td>' +
            '<td class="pcc-vcol-warn">' + editableCellHtml('simple', 'low_warning', p.low_warning, null, 'integer') + '</td>' +
            '</tr>';
        bindCellEvents(simpleLinesBody);
        bindAdjustTriggers(simpleLinesBody);
    }

    function renderSupplierFields(p) {
        syncSupplierFieldDisplay('ibs_model', p.ibs_model);
        syncSupplierFieldDisplay('sm_model', p.sm_model);
        syncSupplierFieldDisplay('product_category', p.product_category);
        syncSupplierFieldDisplay('rate', p.rate);
        syncSupplierFieldDisplay('low_warning', p.low_warning);
    }

    function renderModalContent(p) {
        setImage(p.image || '');
        setText('pccProductIdDisplay', p.oc_product_id || p.product_id);
        setText('pccMainModel', p.lk_model || p.model);
        setText('pccProductType', isVariable(p) ? 'Variable Product' : 'Simple Product');
        renderSupplierFields(p);
        if (isVariable(p)) {
            toggleVariableLayout(true);
            renderVariantLinesTable(p);
        } else {
            toggleVariableLayout(false);
            renderSimpleLinesTable(p);
        }
    }

    function refreshAllDisplays() {
        if (!workingProduct) return;
        renderSupplierFields(workingProduct);
        if (isVariable(workingProduct)) renderVariantLinesTable(workingProduct);
        else renderSimpleLinesTable(workingProduct);
    }

    function startCellEdit(cell) {
        if (cell.classList.contains('is-editing')) return;
        var field = cell.getAttribute('data-editable');
        var scope = cell.getAttribute('data-scope');
        var indexRaw = cell.getAttribute('data-index');
        var index = indexRaw === '' ? null : parseInt(indexRaw, 10);
        var inputType = cell.getAttribute('data-input-type') || 'text';
        if (inputType === 'category') {
            startCategoryFieldEdit(cell, scope, index);
            return;
        }
        var current = storedValue(scope, index, field);
        var currentStr = current == null ? '' : String(current);
        cell.classList.add('is-editing');
        var step = field === 'rate' ? ' step="0.01" min="0"' : (inputType === 'integer' ? ' min="0"' : '');
        var type = inputType === 'decimal' || inputType === 'integer' || field === 'rate' || field === 'ibs_stock' || field === 'low_warning' ? 'number' : 'text';
        cell.innerHTML = '<input type="' + type + '" class="form-input pcc-variant-inline-input"' + step + ' value="' + esc(currentStr) + '">';
        var input = cell.querySelector('input');
        if (!input) return;
        input.focus();
        input.select();

        function finish(rawValue, reason) {
            cell.classList.remove('is-editing');
            if (field === 'ibs_stock') {
                var newVal = rawValue === '' ? null : parseInt(rawValue, 10);
                if (!stockValueChanged(current, newVal)) {
                    renderCellDisplay(cell, scope, index, field);
                    return;
                }
                if (hasIbsStockValue(current) && !reason) {
                    renderCellDisplay(cell, scope, index, field);
                    return;
                }
                var stockChange = buildInlineSetChange(scope, index, field, rawValue, inputType);
                if (reason) stockChange.reason = reason;
                queueChange(stockChange);
                markRowDirty(cell.closest('tr') || cell.closest('.pcc-v202-field-row'));
                renderCellDisplay(cell, scope, index, field);
                return;
            }
            var change = buildInlineSetChange(scope, index, field, rawValue, inputType);
            queueChange(change);
            markRowDirty(cell.closest('tr') || cell.closest('.pcc-v202-field-row'));
            renderCellDisplay(cell, scope, index, field);
            if (scope === 'parent') syncSupplierFieldDisplay(field, change.computedValue);
        }

        function cancel() {
            cell.classList.remove('is-editing');
            renderCellDisplay(cell, scope, index, field);
        }

        input.addEventListener('blur', function () {
            var raw = input.value.trim();
            if (field === 'ibs_stock') {
                var newVal = raw === '' ? null : parseInt(raw, 10);
                if (stockValueChanged(current, newVal)) {
                    if (hasIbsStockValue(current)) {
                        promptStockReason(function (reason) {
                            if (!reason) { cancel(); return; }
                            finish(raw, reason);
                        });
                        return;
                    }
                    finish(raw, null);
                    return;
                }
            }
            finish(raw, null);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); input.blur(); }
            if (event.key === 'Escape') { event.preventDefault(); cancel(); }
        });
    }

    function renderCellDisplay(cell, scope, index, field) {
        if (field === 'rate' && scope === 'variant') {
            var effective = effectiveRate(scope, index);
            cell.innerHTML = effective == null || effective === '' ? chipHtml('') : esc(formatRate(effective));
            return;
        }
        var value = storedValue(scope, index, field);
        if (field === 'rate') cell.innerHTML = trimVal(value) === '' ? chipHtml('') : esc(formatRate(value));
        else if (field === 'ibs_stock') cell.innerHTML = trimVal(value) === '' ? chipHtml('') : esc(formatStock(value));
        else if (field === 'low_warning') cell.textContent = displayLowWarning(scope, index, value);
        else if (field === 'product_category') cell.innerHTML = trimVal(value) === '' ? chipHtml('') : esc(displayVal(value));
        else cell.innerHTML = trimVal(value) === '' ? chipHtml('') : esc(displayVal(value));
    }

    function startCategoryFieldEdit(displayEl, scope, index) {
        if (displayEl.classList.contains('is-editing')) return;
        scope = scope || 'parent';
        var current = storedValue(scope, index, 'product_category') || '';
        displayEl.classList.add('is-editing', 'pcc-category-editing');

        function applyCategoryValue(raw) {
            var value = ensureCategoryOption(raw);
            var change = buildInlineSetChange(scope, index, 'product_category', value, 'category');
            queueChange(change);
            displayEl.classList.remove('is-editing', 'pcc-category-editing');
            if (scope === 'parent') syncSupplierFieldDisplay('product_category', change.computedValue);
            else renderCellDisplay(displayEl, scope, index, 'product_category');
            markRowDirty(displayEl.closest('.pcc-v202-field-row') || displayEl.closest('tr'));
        }

        function exitEdit() {
            displayEl.classList.remove('is-editing', 'pcc-category-editing');
            if (scope === 'parent') syncSupplierFieldDisplay('product_category', current);
            else renderCellDisplay(displayEl, scope, index, 'product_category');
        }

        function renderSelectView() {
            var html = '<select class="form-input form-input-compact pcc-category-select"><option value="">Not set</option>';
            categoryOptions.forEach(function (opt) {
                html += '<option value="' + esc(opt) + '"' + (opt === current ? ' selected' : '') + '>' + esc(opt) + '</option>';
            });
            html += '<option value="__new__">+ Add new category</option></select>';
            displayEl.innerHTML = html;
            var select = displayEl.querySelector('.pcc-category-select');
            if (!select) return;
            select.focus();

            select.addEventListener('change', function () {
                if (select.value === '__new__') {
                    renderAddNewView();
                    return;
                }
                applyCategoryValue(select.value);
            });

            select.addEventListener('blur', function () {
                window.setTimeout(function () {
                    if (displayEl.contains(document.activeElement)) return;
                    if (select.value === '__new__') return;
                    applyCategoryValue(select.value);
                }, 150);
            });

            select.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (select.value === '__new__') {
                        renderAddNewView();
                        return;
                    }
                    applyCategoryValue(select.value);
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    exitEdit();
                }
            });
        }

        function renderAddNewView() {
            displayEl.innerHTML =
                '<div class="pcc-category-add-row">' +
                '<input type="text" class="form-input form-input-compact pcc-category-new-input" placeholder="New category name" autocomplete="off">' +
                '<button type="button" class="pcc-category-add-btn" data-action="save">Save</button>' +
                '<button type="button" class="pcc-category-add-btn pcc-category-add-btn-ghost" data-action="cancel">Cancel</button>' +
                '</div>';
            var input = displayEl.querySelector('.pcc-category-new-input');
            var saveBtn = displayEl.querySelector('[data-action="save"]');
            var cancelBtn = displayEl.querySelector('[data-action="cancel"]');
            if (!input) return;
            input.focus();

            function trySave() {
                var name = trimVal(input.value);
                if (name === '') {
                    input.focus();
                    return;
                }
                var i;
                for (i = 0; i < categoryOptions.length; i++) {
                    if (categoryOptions[i].localeCompare(name, undefined, { sensitivity: 'base' }) === 0) {
                        name = categoryOptions[i];
                        break;
                    }
                }
                applyCategoryValue(name);
            }

            saveBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                trySave();
            });
            cancelBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                renderSelectView();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    trySave();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    renderSelectView();
                }
            });
        }

        renderSelectView();
    }

    function startSupplierFieldEdit(displayEl) {
        if (displayEl.classList.contains('is-editing')) return;
        var field = displayEl.getAttribute('data-field');
        var scope = displayEl.getAttribute('data-scope') || 'parent';
        var inputType = displayEl.getAttribute('data-input-type') || 'text';
        if (field === 'product_category' || inputType === 'category') {
            startCategoryFieldEdit(displayEl, scope, null);
            return;
        }
        var current = getWorkingValue(scope, null, field);
        var currentStr = current == null ? '' : String(current);
        displayEl.classList.add('is-editing');
        var type = inputType === 'decimal' || inputType === 'integer' || field === 'rate' || field === 'low_warning' ? 'number' : 'text';
        var step = field === 'rate' ? ' step="0.01" min="0"' : (type === 'number' ? ' min="0"' : '');
        displayEl.innerHTML = '<input type="' + type + '" class="form-input form-input-compact pcc-supplier-inline-input" value="' + esc(currentStr) + '"' + step + '>';
        var control = displayEl.querySelector('input');
        if (!control) return;
        control.focus();
        control.select();

        function finish() {
            var raw = control.value.trim();
            var change = buildInlineSetChange(scope, null, field, raw, inputType);
            queueChange(change);
            displayEl.classList.remove('is-editing');
            syncSupplierFieldDisplay(field, change.computedValue);
            markRowDirty(displayEl.closest('.pcc-v202-field-row'));
        }

        function cancel() {
            displayEl.classList.remove('is-editing');
            syncSupplierFieldDisplay(field, current);
        }

        control.addEventListener('blur', finish);
        control.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); control.blur(); }
            if (event.key === 'Escape') { event.preventDefault(); cancel(); }
        });
    }

    function bindCellEvents(container) {
        /* cells use modal-level delegation */
    }

    function bindAdjustTriggers(container) {
        /* adjust triggers use modal-level delegation */
    }

    function bindSupplierFieldEvents() {
        /* supplier fields use modal-level delegation */
    }

    function composeAdjustMode(mode, changeType, method) {
        if (mode === 'quantity') return changeType;
        return (method === 'percent' ? 'percent' : 'fixed') + (changeType === 'increase' ? '_plus' : '_minus');
    }

    function applyCostAdjust(current, type, amount) {
        current = parseFloat(current || 0) || 0;
        amount = parseFloat(amount || 0) || 0;
        if (type === 'increase' || type === 'fixed_plus') return Math.max(0, Math.round((current + amount) * 100) / 100);
        if (type === 'decrease' || type === 'fixed_minus') return Math.max(0, Math.round((current - amount) * 100) / 100);
        if (type === 'percent_plus') return Math.max(0, Math.round(current * (1 + amount / 100) * 100) / 100);
        if (type === 'percent_minus') return Math.max(0, Math.round(current * (1 - amount / 100) * 100) / 100);
        return Math.max(0, amount);
    }

    function applyStockAdjust(current, type, amount) {
        current = parseInt(current || 0, 10) || 0;
        amount = parseInt(amount || 0, 10) || 0;
        if (type === 'increase') return Math.max(0, current + amount);
        if (type === 'decrease') return Math.max(0, current - amount);
        return Math.max(0, amount);
    }

    function setAdjustPopoverMode(mode, reasonOnly) {
        var methodWrap = document.getElementById('pccAdjustMethodWrap');
        var reasonWrap = document.getElementById('pccAdjustReasonWrap');
        var noteWrap = document.getElementById('pccAdjustNoteWrap');
        var changeTypeWrap = document.getElementById('pccAdjustChangeTypeWrap');
        var amountWrap = document.getElementById('pccAdjustAmountWrap');
        var currentWrap = document.getElementById('pccAdjustCurrentWrap');
        var amountLabel = document.getElementById('pccAdjustAmountLabel');
        var amountEl = document.getElementById('pccAdjustAmount');
        var title = document.getElementById('pccAdjustTitle');
        var preview = document.getElementById('pccAdjustPreview');
        if (reasonOnly) {
            if (currentWrap) currentWrap.hidden = true;
            if (changeTypeWrap) changeTypeWrap.hidden = true;
            if (methodWrap) methodWrap.hidden = true;
            if (amountWrap) amountWrap.hidden = true;
            if (noteWrap) noteWrap.hidden = true;
            if (preview) preview.hidden = true;
            if (reasonWrap) reasonWrap.hidden = false;
            if (title) title.textContent = 'Stock reason required';
            return;
        }
        if (currentWrap) currentWrap.hidden = false;
        if (changeTypeWrap) changeTypeWrap.hidden = false;
        if (amountWrap) amountWrap.hidden = false;
        if (preview) preview.hidden = false;
        if (methodWrap) methodWrap.hidden = mode !== 'price';
        if (reasonWrap) reasonWrap.hidden = mode === 'price';
        if (noteWrap) noteWrap.hidden = mode !== 'price';
        if (amountLabel) amountLabel.textContent = mode === 'price' ? 'Amount' : 'Quantity';
        if (amountEl) {
            amountEl.step = mode === 'price' ? '0.01' : '1';
            amountEl.placeholder = mode === 'price' ? 'Enter amount' : 'Enter quantity';
        }
        if (title) title.textContent = mode === 'price' ? 'Adjust Rate' : 'Adjust Stock';
    }

    function showAdjustModal(context) {
        adjustContext = context;
        if (!adjustPopover) return;
        document.getElementById('pccAdjustChangeType').value = 'increase';
        document.getElementById('pccAdjustMethod').value = 'fixed';
        document.getElementById('pccAdjustAmount').value = '';
        document.getElementById('pccAdjustReason').value = '';
        document.getElementById('pccAdjustNote').value = '';
        setAdjustPopoverMode(context.mode, false);
        var currentEl = document.getElementById('pccAdjustCurrent');
        if (currentEl) {
            currentEl.value = formatAdjustCurrentValue(context.mode, context.baseValue);
            currentEl.readOnly = true;
            currentEl.disabled = true;
        }
        updateAdjustPreview();
        adjustPopover.hidden = false;
        document.getElementById('pccAdjustAmount').focus();
    }

    function hideAdjustPopover() {
        if (adjustPopover) adjustPopover.hidden = true;
        adjustContext = null;
        var currentEl = document.getElementById('pccAdjustCurrent');
        if (currentEl) currentEl.disabled = false;
        setAdjustPopoverMode('price', false);
    }

    function promptStockReason(callback) {
        adjustContext = { inlineReasonOnly: true, onApply: callback };
        document.getElementById('pccAdjustReason').value = '';
        setAdjustPopoverMode('quantity', true);
        adjustPopover.hidden = false;
        document.getElementById('pccAdjustReason').focus();
    }

    function updateAdjustPreview() {
        if (!adjustContext) return;
        var changeType = document.getElementById('pccAdjustChangeType').value;
        var method = document.getElementById('pccAdjustMethod').value;
        var amount = document.getElementById('pccAdjustAmount').value;
        var preview = document.getElementById('pccAdjustPreview');
        if (!preview) return;
        var composed = composeAdjustMode(adjustContext.mode, changeType, method);
        var base = adjustContext.mode === 'price'
            ? (parseFloat(adjustContext.baseValue || 0) || 0)
            : numericStockValue(adjustContext.baseValue);
        var next = adjustContext.mode === 'price'
            ? applyCostAdjust(base, composed, amount)
            : applyStockAdjust(base, composed, amount);
        preview.textContent = 'Preview: ' + base + ' → ' + next;
    }

    function applyAdjustPopover() {
        if (!adjustContext) return;
        if (adjustContext.inlineReasonOnly) {
            var reason = trimVal(document.getElementById('pccAdjustReason').value);
            if (reason === '' || stockReasons.indexOf(reason) === -1) {
                window.alert('Select a stock reason.');
                return;
            }
            if (typeof adjustContext.onApply === 'function') adjustContext.onApply(reason);
            hideAdjustPopover();
            return;
        }
        var changeType = document.getElementById('pccAdjustChangeType').value;
        var method = document.getElementById('pccAdjustMethod').value;
        var amount = document.getElementById('pccAdjustAmount').value;
        var reason = trimVal(document.getElementById('pccAdjustReason').value);
        var note = trimVal(document.getElementById('pccAdjustNote').value);
        if (trimVal(amount) === '' || parseFloat(amount) < 0) {
            window.alert('A valid adjustment amount is required.');
            return;
        }
        if (adjustContext.mode === 'quantity' && (reason === '' || stockReasons.indexOf(reason) === -1)) {
            window.alert('Select a stock reason before applying this adjustment.');
            return;
        }
        var composed = composeAdjustMode(adjustContext.mode, changeType, method);
        var adjustBase = adjustContext.mode === 'price'
            ? (parseFloat(adjustContext.baseValue || 0) || 0)
            : numericStockValue(adjustContext.baseValue);
        var next = adjustContext.mode === 'price'
            ? applyCostAdjust(adjustBase, composed, amount)
            : applyStockAdjust(adjustBase, composed, amount);
        var change;
        if (adjustContext.mode === 'price' && method === 'percent') {
            change = {
                scope: adjustContext.scope,
                field: 'rate',
                mode: 'set',
                value: next,
                computedValue: next
            };
        } else {
            change = {
                scope: adjustContext.scope,
                field: adjustContext.field,
                mode: changeType,
                amount: Number(amount),
                computedValue: next
            };
        }
        if (adjustContext.index != null) change.index = adjustContext.index;
        if (adjustContext.mode === 'quantity') change.reason = reason;
        if (note) change.note = note;
        queueChange(change);
        refreshAllDisplays();
        hideAdjustPopover();
    }

    function activateTab(tab) {
        document.querySelectorAll('.pcc-tab').forEach(function (btn) {
            var active = btn.getAttribute('data-pcc-tab') === tab;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.pcc-tab-panel').forEach(function (panel) {
            var active = panel.getAttribute('data-pcc-panel') === tab;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
        if (tab === 'history' && workingProduct) loadHistory(productId(workingProduct));
    }

    function renderHistoryRows(history) {
        var tbody = document.getElementById('pccHistoryRows');
        if (!tbody) return;
        var parentModel = workingProduct ? (workingProduct.lk_model || workingProduct.model || 'Product') : 'Product';
        var rows = [];
        (history.rate || []).forEach(function (row) {
            rows.push({
                date: row.date || '—',
                time: row.time || '—',
                model: row.variant_id ? String(row.variant_id) : parentModel,
                type: 'Rate Change',
                old: row.old_rate ?? '—',
                neu: row.new_rate,
                change: row.difference,
                reason: '—',
                note: row.note || '—',
                user: row.user || 'System',
                sortAt: row.sort_at || ''
            });
        });
        (history.stock || []).forEach(function (row) {
            var isInitial = row.old_stock == null && (row.reason == null || row.reason === '');
            rows.push({
                date: row.date || '—',
                time: row.time || '—',
                model: row.variant_id ? String(row.variant_id) : parentModel,
                type: isInitial ? 'Initial Stock Set' : 'Stock Adjustment',
                old: row.old_stock ?? '—',
                neu: row.new_stock,
                change: row.difference,
                reason: isInitial ? 'Initial Set' : (row.reason || '—'),
                note: row.note || '—',
                user: row.user || 'System',
                sortAt: row.sort_at || ''
            });
        });
        rows.sort(function (a, b) { return String(b.sortAt).localeCompare(String(a.sortAt)); });
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="pcc-muted-cell">No rate or stock history recorded yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (row) {
            return '<tr><td>' + esc(row.date) + '</td><td>' + esc(row.time) + '</td><td>' + esc(row.model) + '</td><td>' + esc(row.type) + '</td><td class="pcc-num">' + esc(String(row.old)) + '</td><td class="pcc-num">' + esc(String(row.neu)) + '</td><td class="pcc-num">' + esc(String(row.change)) + '</td><td>' + esc(row.reason) + '</td><td>' + esc(row.note) + '</td><td>' + esc(row.user) + '</td></tr>';
        }).join('');
    }

    function loadHistory(pid) {
        if (historyCache[pid]) {
            renderHistoryRows(historyCache[pid]);
            return;
        }
        var tbody = document.getElementById('pccHistoryRows');
        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="pcc-muted-cell">Loading history…</td></tr>';
        fetch(historyUrl + '?product_id=' + encodeURIComponent(pid), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="pcc-muted-cell">Could not load history.</td></tr>';
                    return;
                }
                historyCache[pid] = d.history || {};
                historyCounts[pid] = d.history_count || 0;
                if (Array.isArray(d.categories)) categoryOptions = d.categories.slice();
                renderHistoryRows(historyCache[pid]);
            })
            .catch(function () {
                if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="pcc-muted-cell">Could not load history.</td></tr>';
            });
    }

    function buildPayload() {
        return {
            product_index: currentIndex,
            changes: pendingChanges.map(function (c) {
                var out = { scope: c.scope, field: c.field };
                if (c.scope === 'variant') out.index = c.index;
                if (c.mode === 'set') out.value = c.value;
                else { out.mode = c.mode; out.amount = c.amount; }
                if (c.reason) out.reason = c.reason;
                if (c.note) out.note = c.note;
                return out;
            })
        };
    }

    function restoreBaseline() {
        if (!baselineProduct) return;
        workingProduct = normalizeWorkingProduct(baselineProduct);
        pendingChanges = [];
        clearDirtyHighlights();
        renderModalContent(workingProduct);
        setDirty(false);
    }

    function openModal(index) {
        var p = products[index];
        if (!p) return;
        currentIndex = index;
        workingProduct = normalizeWorkingProduct(p);
        baselineProduct = normalizeWorkingProduct(p);
        pendingChanges = [];
        document.getElementById('control-product-index').value = index;
        showModalError('');
        setDirty(false);
        hideAdjustPopover();
        renderModalContent(workingProduct);
        loadHistory(productId(p));
        activateTab('details');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeModal(force) {
        if (!force && isDirty && !window.confirm('Discard unsaved changes?')) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        hideAdjustPopover();
        currentIndex = null;
        workingProduct = null;
        baselineProduct = null;
        pendingChanges = [];
        setDirty(false);
    }

    function updateListingRow(index, product) {
        products[index] = product;
        var pid = productId(product);
        var group = document.getElementById('group-product-row-' + index);
        if (!group) return;
        var row = document.getElementById('product-row-' + index);
        if (!row) return;
        function setCellOn(rowEl, key, val) {
            var cell = rowEl.querySelector('[data-cell="' + key + '"]');
            if (cell) cell.textContent = val;
        }
        setCellOn(row, 'ibs_model', displayVal(product.ibs_model));
        setCellOn(row, 'sm_model', displayVal(product.sm_model));
        setCellOn(row, 'product_category', displayVal(product.product_category));
        setCellOn(row, 'rate', formatRate(product.rate));
        setCellOn(row, 'ibs_stock', formatStock(product.ibs_stock));
        var btn = group.querySelector('[data-control-open]');
        if (btn && historyCounts[pid] !== undefined) {
            btn.textContent = historyCounts[pid] > 0 ? 'Edit (' + historyCounts[pid] + ')' : 'Edit';
        }
        (product.options || []).forEach(function (opt, i) {
            var vRow = document.getElementById('product-row-' + index + '-variant-' + i);
            if (!vRow) return;
            setCellOn(vRow, 'ibs_model', displayVal(opt.ibs_model));
            setCellOn(vRow, 'sm_model', displayVal(opt.sm_model));
            setCellOn(vRow, 'ibs_stock', formatStock(opt.ibs_stock));
        });
    }

    document.querySelectorAll('.pcc-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.getAttribute('data-pcc-tab'));
        });
    });

    document.getElementById('pccAdjustCancel').addEventListener('click', hideAdjustPopover);
    document.getElementById('pccAdjustApply').addEventListener('click', applyAdjustPopover);
    ['pccAdjustChangeType', 'pccAdjustMethod', 'pccAdjustAmount'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateAdjustPreview);
        if (el) el.addEventListener('change', updateAdjustPreview);
    });

    if (cancelBtn) cancelBtn.addEventListener('click', restoreBaseline);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (currentIndex === null || !pendingChanges.length) return;
        saveBtn.disabled = true;
        showModalError('');
        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(buildPayload())
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.success) {
                    showModalError(res.data.message || 'Save failed.');
                    saveBtn.disabled = false;
                    return;
                }
                var pid = productId(res.data.product);
                historyCache[pid] = res.data.history || {};
                historyCounts[pid] = res.data.history_count || 0;
                updateListingRow(currentIndex, res.data.product);
                if (res.data.summary) {
                    document.querySelectorAll('[data-summary-key]').forEach(function (el) {
                        var key = el.getAttribute('data-summary-key');
                        if (res.data.summary[key] !== undefined) el.textContent = res.data.summary[key];
                    });
                }
                closeModal(true);
            })
            .catch(function () {
                showModalError('Save failed. Please try again.');
                saveBtn.disabled = false;
            });
    });

    document.querySelectorAll('[data-close-control-modal]').forEach(function (el) {
        el.addEventListener('click', function () { closeModal(); });
    });

    document.querySelectorAll('[data-control-open]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(parseInt(el.getAttribute('data-product-index') || '0', 10));
        });
    });

    document.querySelectorAll('.product-map-parent-row[data-control-open-row]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('.expand-toggle, [data-control-open], button, a')) return;
            openModal(parseInt(row.getAttribute('data-product-index') || '0', 10));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            if (adjustPopover && !adjustPopover.hidden) hideAdjustPopover();
            else closeModal();
        }
    });

    modal.addEventListener('dblclick', function (e) {
        var cell = e.target.closest('.pcc-cell-value[data-editable]');
        if (cell) startCellEdit(cell);
        var supplier = e.target.closest('.pcc-dblclick-edit[data-scope="parent"]');
        if (supplier) startSupplierFieldEdit(supplier);
    });

    modal.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var cell = e.target.closest('.pcc-cell-value[data-editable]');
        if (cell) { e.preventDefault(); startCellEdit(cell); }
        var supplier = e.target.closest('.pcc-dblclick-edit[data-scope="parent"]');
        if (supplier) { e.preventDefault(); startSupplierFieldEdit(supplier); }
    });

    modal.addEventListener('click', function (e) {
        var btn = e.target.closest('.pcc-v202-adjust-trigger');
        if (!btn) return;
        e.stopPropagation();
        var mode = btn.getAttribute('data-adjust-mode') || 'price';
        var scope = btn.getAttribute('data-adjust-scope') || 'parent';
        var indexRaw = btn.getAttribute('data-adjust-index');
        var index = indexRaw === '' ? null : parseInt(indexRaw, 10);
        if (mode === 'quantity') {
            var stockVal = storedValue(scope, index, 'ibs_stock');
            if (!hasIbsStockValue(stockVal)) {
                window.alert('Double-click IBS Stock to set the initial quantity.');
                return;
            }
            showAdjustModal({
                scope: scope,
                mode: 'quantity',
                index: index,
                field: 'ibs_stock',
                baseValue: numericStockValue(stockVal),
            });
            return;
        }
        var base = effectiveRate(scope, index);
        if (base == null || base === '') base = 0;
        showAdjustModal({ scope: scope, mode: 'price', index: index, field: 'rate', baseValue: base });
    });

    bindSupplierFieldEvents();

    window.productMapOpenControl = openModal;
})();
</script>
@endpush
