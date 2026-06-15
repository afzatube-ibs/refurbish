@push('scripts')
<script>
(function () {
    var products = @json($products ?? []);
    var activityByProduct = @json(collect($previewActivity ?? [])->groupBy('product_id')->map(fn ($items) => array_values($items->all()))->all());
    var saveUrl = @json(route('product-map.control.save'));
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    var modal = document.getElementById('product-control-modal');
    var form = document.getElementById('product-control-form');
    var rowsTbody = document.getElementById('control-rows-tbody');
    var rowsTitle = document.getElementById('control-rows-title');
    var parentActivityList = document.getElementById('control-parent-activity');
    var variantActivityList = document.getElementById('control-variant-activity');
    var errorEl = document.getElementById('control-form-error');
    var saveBtn = document.getElementById('control-save-btn');
    var parentLowWarningInput = document.getElementById('control-parent-low-warning');
    var popover = document.getElementById('control-adjust-popover');

    var currentIndex = null;
    var formSnapshot = null;
    var pendingAdjustments = {};
    var displayValues = {};
    var activeFieldWrap = null;

    function displayField(value) {
        if (value === null || value === undefined || value === '') return '—';
        return String(value);
    }

    function formatRate(value) {
        if (value === null || value === undefined || value === '') return '—';
        return Number(value).toFixed(2);
    }

    function displayIbsStock(value) {
        if (value === null || value === undefined || value === '' || Number(value) === 0) return '—';
        return String(parseInt(value, 10));
    }

    function isBlank(value) {
        return value === null || value === undefined || String(value).trim() === '' || value === '—';
    }

    function effectiveLowWarning(option, parentValue) {
        if (option.low_warning !== null && option.low_warning !== undefined) return option.low_warning;
        return parentValue;
    }

    function parentLowValue() {
        return parentLowWarningInput.value || 5;
    }

    function escapeHtml(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#39;');
    }

    function renderHealthBadge(health) {
        health = health || { status: 'ok', label: 'OK', issues: [] };
        var issues = health.issues || [];
        var title = issues.length ? issues.join('; ') : 'No issues';
        if (health.status === 'ok') {
            return '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">OK</span>';
        }
        return '<div class="inline-flex flex-col items-center gap-0.5" title="' + escapeAttr(title) + '">' +
            '<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">' + escapeHtml(health.label || 'Review') + '</span>' +
            '<span class="text-[10px] leading-tight text-amber-700 max-w-[8rem] truncate">' + escapeHtml(issues[0] || '') + '</span></div>';
    }

    function summaryThumbHtml(imageUrl) {
        if (imageUrl) {
            return '<img src="' + escapeAttr(imageUrl) + '" alt="" class="product-control-thumb product-control-thumb--summary">';
        }
        return '<span class="product-control-thumb product-control-thumb--summary product-control-thumb--empty">—</span>';
    }

    function rowThumbHtml(imageUrl) {
        if (imageUrl) {
            return '<img src="' + escapeAttr(imageUrl) + '" alt="" class="product-control-thumb product-control-thumb--row">';
        }
        return '<span class="product-control-thumb product-control-thumb--row product-control-thumb--empty">—</span>';
    }

    function valueColumnHtml(field, val) {
        return '<div class="product-control-value-cell" data-value-field="' + escapeAttr(field) + '">' +
            '<span class="product-control-value-text">' + escapeHtml(val) + '</span></div>';
    }

    function actionColumnHtml(rowKey) {
        return '<button type="button" class="product-control-adjust-btn" data-row-adjust="' + escapeAttr(rowKey) + '">' +
            '<span class="product-control-adjust-icon" aria-hidden="true">⚙</span> Adjust</button>';
    }

    function fieldWrapHtml(innerHtml, fieldKey) {
        return '<div class="product-control-field-wrap" data-field-key="' + escapeAttr(fieldKey) + '">' +
            innerHtml +
        '</div>';
    }

    function lowWarningLockHtml(option, parentLow, rowKey) {
        var inherited = option.low_warning === null || option.low_warning === undefined;
        var effectiveLow = effectiveLowWarning(option, parentLow);

        if (inherited) {
            return fieldWrapHtml(
                '<div class="product-control-low-lock" data-inherited="true">' +
                    '<button type="button" class="product-control-lock-btn" title="Inherit parent (' + parentLow + ')">🔒</button>' +
                    '<span class="product-control-low-inherit-text">Inherit (' + parentLow + ')</span>' +
                    '<input type="number" min="0" step="1" class="product-control-input product-control-input--compact variant-low-warning hidden" value="' + effectiveLow + '">' +
                '</div>',
                rowKey + '.low_warning'
            );
        }

        return fieldWrapHtml(
            '<div class="product-control-low-lock" data-inherited="false">' +
                '<button type="button" class="product-control-lock-btn product-control-lock-btn--unlocked" title="Custom value — click to inherit parent">🔓</button>' +
                '<span class="product-control-low-inherit-text hidden">Inherit (' + parentLow + ')</span>' +
                '<input type="number" min="0" step="1" class="product-control-input product-control-input--compact variant-low-warning" value="' + effectiveLow + '">' +
            '</div>',
            rowKey + '.low_warning'
        );
    }

    function lockFieldWrap(wrap) {
        if (!wrap) return;
        wrap.classList.remove('is-editing');
        wrap.querySelectorAll('.control-lockable').forEach(function (input) {
            input.readOnly = true;
            input.classList.add('product-control-input--locked');
        });
        if (activeFieldWrap === wrap) activeFieldWrap = null;
    }

    function unlockFieldWrap(wrap) {
        if (!wrap || wrap.classList.contains('is-editing')) return;
        if (activeFieldWrap && activeFieldWrap !== wrap) lockFieldWrap(activeFieldWrap);

        wrap.classList.add('is-editing');
        activeFieldWrap = wrap;
        wrap.querySelectorAll('.control-lockable').forEach(function (input) {
            input.readOnly = false;
            input.classList.remove('product-control-input--locked');
        });

        updateFormState();
    }

    function initLockableWrap(wrap) {
        if (wrap.querySelector('.product-control-low-lock')) return;

        wrap.querySelectorAll('.control-lockable').forEach(function (input) {
            input.readOnly = true;
            input.classList.add('product-control-input--locked');
            input.addEventListener('blur', updateFormState);
            input.addEventListener('input', updateFormState);
            input.addEventListener('change', updateFormState);
        });
        wrap.addEventListener('dblclick', function (e) {
            if (e.target.closest('.product-control-adjust-btn, .product-control-lock-btn')) return;
            unlockFieldWrap(wrap);
        });
    }

    function setLowWarningInherited(wrap, inherited) {
        var lockEl = wrap.querySelector('.product-control-low-lock');
        var btn = wrap.querySelector('.product-control-lock-btn');
        var input = wrap.querySelector('.variant-low-warning');
        var inheritText = wrap.querySelector('.product-control-low-inherit-text');
        if (!lockEl || !btn) return;

        lockEl.dataset.inherited = inherited ? 'true' : 'false';

        if (inherited) {
            btn.textContent = '🔒';
            btn.title = 'Inherit parent (' + parentLowValue() + ')';
            btn.classList.remove('product-control-lock-btn--unlocked');
            if (inheritText) {
                inheritText.textContent = 'Inherit (' + parentLowValue() + ')';
                inheritText.classList.remove('hidden');
            }
            if (input) input.classList.add('hidden');
        } else {
            btn.textContent = '🔓';
            btn.title = 'Custom value — click to inherit parent';
            btn.classList.add('product-control-lock-btn--unlocked');
            if (inheritText) inheritText.classList.add('hidden');
            if (input) {
                input.classList.remove('hidden');
                if (isBlank(input.value)) input.value = parentLowValue();
            }
        }

        updateFormState();
    }

    function initLowWarningLock(wrap) {
        var btn = wrap.querySelector('.product-control-lock-btn');
        var input = wrap.querySelector('.variant-low-warning');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var inherited = wrap.querySelector('.product-control-low-lock')?.dataset.inherited === 'true';
            setLowWarningInherited(wrap, !inherited);
        });

        if (input) {
            input.addEventListener('input', updateFormState);
            input.addEventListener('blur', updateFormState);
        }
    }

    function initSummaryFields() {
        form.querySelectorAll('.product-control-summary .product-control-field-wrap').forEach(initLockableWrap);
    }

    function computeDisplayValue(baseValue, adjustment, isRate) {
        if (!adjustment || !adjustment.mode) return baseValue;
        var base = baseValue === null || baseValue === undefined || baseValue === '' ? 0 : Number(baseValue);
        var amount = Number(adjustment.amount || 0);
        var result = base;
        if (adjustment.mode === 'set') result = amount;
        else if (adjustment.mode === 'increase') result = base + amount;
        else if (adjustment.mode === 'decrease') result = base - amount;
        return isRate ? Math.round(result * 100) / 100 : Math.round(result);
    }

    function getRowDisplayValues(rowKey, baseRate, baseStock) {
        var adj = pendingAdjustments[rowKey] || {};
        return {
            rate: formatRate(computeDisplayValue(baseRate, adj.rate, true)),
            stock: displayIbsStock(computeDisplayValue(
                baseStock === null || baseStock === 0 ? null : baseStock,
                adj.stock,
                false
            ))
        };
    }

    function refreshRowValueDisplays() {
        rowsTbody.querySelectorAll('tr.product-control-row').forEach(function (row) {
            var rowKey = row.dataset.rowKey;
            if (!rowKey || !displayValues[rowKey]) return;
            var vals = getRowDisplayValues(rowKey, displayValues[rowKey].baseRate, displayValues[rowKey].baseStock);
            var rateEl = row.querySelector('[data-value-field="rate"] .product-control-value-text');
            var stockEl = row.querySelector('[data-value-field="ibs_stock"] .product-control-value-text');
            if (rateEl) rateEl.textContent = vals.rate;
            if (stockEl) stockEl.textContent = vals.stock;
        });

        rowsTbody.querySelectorAll('.product-control-low-inherit-text').forEach(function (el) {
            if (!el.classList.contains('hidden')) {
                el.textContent = 'Inherit (' + parentLowValue() + ')';
            }
        });
    }

    function renderRowsTable(product) {
        var options = product.options || product.variants || [];
        var parentLow = product.low_warning ?? 5;
        rowsTbody.innerHTML = '';

        if (options.length === 0) {
            rowsTitle.textContent = 'Rate & Stock';
            var rowKey = 'row.parent';
            displayValues[rowKey] = { baseRate: product.rate, baseStock: product.ibs_stock };
            var vals = getRowDisplayValues(rowKey, product.rate, product.ibs_stock);
            var row = document.createElement('tr');
            row.className = 'product-control-row';
            row.dataset.rowKind = 'parent';
            row.dataset.rowKey = rowKey;
            row.innerHTML =
                '<td>' + rowThumbHtml(product.image) + '</td>' +
                '<td class="font-mono text-xs">' + escapeHtml(displayField(product.lk_model || product.model)) + '</td>' +
                '<td class="text-slate-400 text-xs">—</td>' +
                '<td class="text-slate-400 text-xs">—</td>' +
                '<td>' + valueColumnHtml('rate', vals.rate) + '</td>' +
                '<td>' + valueColumnHtml('ibs_stock', vals.stock) + '</td>' +
                '<td class="text-slate-400 text-xs">—</td>' +
                '<td>' + actionColumnHtml(rowKey) + '</td>';
            bindRowAdjust(row);
            rowsTbody.appendChild(row);
            return;
        }

        rowsTitle.textContent = 'Variants (' + options.length + ')';

        options.forEach(function (option, index) {
            var rowKey = 'row.' + index;
            displayValues[rowKey] = { baseRate: option.rate, baseStock: option.ibs_stock };
            var vals = getRowDisplayValues(rowKey, option.rate, option.ibs_stock);

            var row = document.createElement('tr');
            row.className = 'product-control-row';
            row.dataset.rowKind = 'variant';
            row.dataset.variantIndex = String(index);
            row.dataset.rowKey = rowKey;

            row.innerHTML =
                '<td>' + rowThumbHtml(option.image) + '</td>' +
                '<td class="font-mono text-xs">' + escapeHtml(displayField(option.lk_model || option.model)) + '</td>' +
                '<td>' + fieldWrapHtml('<input type="text" class="product-control-input product-control-input--compact variant-ibs-model control-lockable" value="' + escapeAttr(option.ibs_model || '') + '" readonly>', rowKey + '.ibs_model') + '</td>' +
                '<td>' + fieldWrapHtml('<input type="text" class="product-control-input product-control-input--compact variant-sm-model control-lockable" value="' + escapeAttr(option.sm_model || '') + '" readonly>', rowKey + '.sm_model') + '</td>' +
                '<td>' + valueColumnHtml('rate', vals.rate) + '</td>' +
                '<td>' + valueColumnHtml('ibs_stock', vals.stock) + '</td>' +
                '<td>' + lowWarningLockHtml(option, parentLow, rowKey) + '</td>' +
                '<td>' + actionColumnHtml(rowKey) + '</td>';

            row.querySelectorAll('.product-control-field-wrap').forEach(function (wrap) {
                if (wrap.querySelector('.product-control-low-lock')) {
                    initLowWarningLock(wrap);
                } else {
                    initLockableWrap(wrap);
                }
            });

            bindRowAdjust(row);
            rowsTbody.appendChild(row);
        });
    }

    function bindRowAdjust(row) {
        var btn = row.querySelector('[data-row-adjust]');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openAdjustPopover(btn.getAttribute('data-row-adjust'), btn);
        });
    }

    function closePopover() {
        popover.classList.add('hidden');
        document.getElementById('control-popover-rate-error').classList.add('hidden');
        document.getElementById('control-popover-stock-error').classList.add('hidden');
    }

    function toggleStockReasonField() {
        var mode = document.getElementById('control-popover-stock-mode').value;
        document.getElementById('control-popover-reason-wrap').classList.toggle('hidden', !mode);
    }

    function positionPopover(anchor) {
        var rect = anchor.getBoundingClientRect();
        popover.classList.remove('hidden');
        var popRect = popover.querySelector('.product-control-popover__inner').getBoundingClientRect();
        var top = rect.bottom + 6;
        var left = rect.left - popRect.width + rect.width;
        if (left + popRect.width > window.innerWidth - 8) left = window.innerWidth - popRect.width - 8;
        if (top + popRect.height > window.innerHeight - 8) top = rect.top - popRect.height - 6;
        popover.style.top = Math.max(8, top) + 'px';
        popover.style.left = Math.max(8, left) + 'px';
    }

    function openAdjustPopover(rowKey, anchor) {
        var vals = displayValues[rowKey];
        if (!vals) return;

        document.getElementById('control-popover-row-key').value = rowKey;
        var display = getRowDisplayValues(rowKey, vals.baseRate, vals.baseStock);
        document.getElementById('control-popover-rate-current').textContent = display.rate;
        document.getElementById('control-popover-stock-current').textContent = display.stock;

        var existing = pendingAdjustments[rowKey] || {};
        document.getElementById('control-popover-rate-mode').value = existing.rate?.mode || '';
        document.getElementById('control-popover-rate-amount').value = existing.rate?.amount ?? '';
        document.getElementById('control-popover-stock-mode').value = existing.stock?.mode || '';
        document.getElementById('control-popover-stock-amount').value = existing.stock?.amount ?? '';
        document.getElementById('control-popover-stock-reason').value = existing.stock?.reason ?? '';

        document.getElementById('control-popover-rate-error').classList.add('hidden');
        document.getElementById('control-popover-stock-error').classList.add('hidden');
        toggleStockReasonField();
        positionPopover(anchor);
    }

    function applyPopover() {
        var rowKey = document.getElementById('control-popover-row-key').value;
        var rateMode = document.getElementById('control-popover-rate-mode').value;
        var rateAmount = document.getElementById('control-popover-rate-amount').value;
        var stockMode = document.getElementById('control-popover-stock-mode').value;
        var stockAmount = document.getElementById('control-popover-stock-amount').value;
        var stockReason = document.getElementById('control-popover-stock-reason').value;
        var rateError = document.getElementById('control-popover-rate-error');
        var stockError = document.getElementById('control-popover-stock-error');

        rateError.classList.add('hidden');
        stockError.classList.add('hidden');

        if (rateMode && isBlank(rateAmount)) {
            rateError.textContent = 'Amount is required';
            rateError.classList.remove('hidden');
            return;
        }

        if (stockMode) {
            if (isBlank(stockAmount)) {
                stockError.textContent = 'Amount is required';
                stockError.classList.remove('hidden');
                return;
            }
            if (isBlank(stockReason)) {
                stockError.textContent = 'Reason is required';
                stockError.classList.remove('hidden');
                document.getElementById('control-popover-reason-wrap').classList.remove('hidden');
                return;
            }
        }

        if (!pendingAdjustments[rowKey]) pendingAdjustments[rowKey] = {};

        if (rateMode) {
            pendingAdjustments[rowKey].rate = { mode: rateMode, amount: rateAmount };
        } else {
            delete pendingAdjustments[rowKey].rate;
        }

        if (stockMode) {
            pendingAdjustments[rowKey].stock = { mode: stockMode, amount: stockAmount, reason: stockReason };
        } else {
            delete pendingAdjustments[rowKey].stock;
        }

        if (Object.keys(pendingAdjustments[rowKey]).length === 0) {
            delete pendingAdjustments[rowKey];
        }

        closePopover();
        refreshRowValueDisplays();
        updateFormState();
    }

    function captureSnapshot() {
        var snapshot = {
            parent: {
                ibs_model: document.getElementById('control-parent-ibs-model').value,
                sm_model: document.getElementById('control-parent-sm-model').value,
                low_warning: document.getElementById('control-parent-low-warning').value
            },
            rows: [],
            adjustments: JSON.parse(JSON.stringify(pendingAdjustments))
        };

        rowsTbody.querySelectorAll('tr.product-control-row').forEach(function (row) {
            var rowKey = row.dataset.rowKey;
            var lowWrap = row.querySelector('[data-field-key$=".low_warning"]');
            var lockEl = lowWrap ? lowWrap.querySelector('.product-control-low-lock') : null;

            snapshot.rows.push({
                rowKey: rowKey,
                kind: row.dataset.rowKind,
                index: row.dataset.variantIndex || null,
                ibs_model: row.querySelector('.variant-ibs-model')?.value ?? '',
                sm_model: row.querySelector('.variant-sm-model')?.value ?? '',
                low_inherit: lockEl ? lockEl.dataset.inherited === 'true' : true,
                low_warning: lowWrap ? (lowWrap.querySelector('.variant-low-warning')?.value ?? '') : ''
            });
        });

        return snapshot;
    }

    function isFormDirty() {
        if (!formSnapshot) return false;
        return JSON.stringify(captureSnapshot()) !== JSON.stringify(formSnapshot);
    }

    function updateFormState() {
        saveBtn.disabled = !isFormDirty();
        if (!isFormDirty()) errorEl.classList.add('hidden');
        refreshRowValueDisplays();
    }

    function openModal(productIndex) {
        var product = products[productIndex];
        if (!product) return;

        currentIndex = productIndex;
        pendingAdjustments = {};
        displayValues = {};
        formSnapshot = null;
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        saveBtn.disabled = true;
        closePopover();
        if (activeFieldWrap) lockFieldWrap(activeFieldWrap);

        document.getElementById('control-product-index').value = productIndex;
        document.getElementById('control-product-id').textContent = displayField(product.oc_product_id || product.product_id);
        document.getElementById('control-product-model').textContent = displayField(product.lk_model || product.model);
        document.getElementById('control-product-image').innerHTML = summaryThumbHtml(product.image);
        document.getElementById('control-parent-ibs-model').value = product.ibs_model || '';
        document.getElementById('control-parent-sm-model').value = product.sm_model || '';
        document.getElementById('control-parent-low-warning').value = product.low_warning ?? 5;

        renderRowsTable(product);
        initSummaryFields();

        formSnapshot = captureSnapshot();
        renderActivity(product);
        updateFormState();

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        closePopover();
        currentIndex = null;
        formSnapshot = null;
        pendingAdjustments = {};
        displayValues = {};
    }

    function renderActivityEntry(entry) {
        var variant = entry.variant_id ? ' · ' + entry.variant_id : '';
        return '<div class="product-control-activity-item">' +
            '<div class="product-control-activity-meta"><span class="font-medium text-slate-800">' + escapeHtml(entry.field) + variant + '</span>' +
            '<span class="text-xs text-slate-500">' + escapeHtml(entry.timestamp || '') + '</span></div>' +
            '<p class="text-sm text-slate-600">' + escapeHtml(String(entry.old_value ?? '—')) + ' → ' + escapeHtml(String(entry.new_value ?? '—')) +
            ' <span class="text-slate-400">(' + escapeHtml(entry.change_type || 'set') + ')</span></p>' +
            (entry.reason ? '<p class="text-xs text-slate-500">Reason: ' + escapeHtml(entry.reason) + '</p>' : '') +
            '<p class="text-xs text-slate-400">' + escapeHtml(entry.user || 'User') + '</p></div>';
    }

    function renderActivity(product) {
        var productId = String(product.product_id || product.oc_product_id || '');
        var entries = activityByProduct[productId] || [];
        var parentEntries = entries.filter(function (e) { return !e.variant_id; });
        var variantEntries = entries.filter(function (e) { return !!e.variant_id; });

        parentActivityList.innerHTML = parentEntries.length
            ? parentEntries.slice().reverse().map(renderActivityEntry).join('')
            : '<p class="product-control-activity-empty">No parent changes yet.</p>';

        variantActivityList.innerHTML = variantEntries.length
            ? variantEntries.slice().reverse().map(renderActivityEntry).join('')
            : '<p class="product-control-activity-empty">No variant changes yet.</p>';
    }

    function buildPayload() {
        var payload = {
            product_index: currentIndex,
            parent: {
                ibs_model: document.getElementById('control-parent-ibs-model').value,
                sm_model: document.getElementById('control-parent-sm-model').value,
                low_warning: parseInt(document.getElementById('control-parent-low-warning').value || '5', 10)
            },
            variants: []
        };

        var parentAdj = pendingAdjustments['row.parent'] || {};
        if (parentAdj.rate) payload.parent.rate = parentAdj.rate;
        if (parentAdj.stock) payload.parent.ibs_stock = parentAdj.stock;

        rowsTbody.querySelectorAll('tr.product-control-row[data-row-kind="variant"]').forEach(function (row) {
            var index = parseInt(row.dataset.variantIndex || '0', 10);
            var rowKey = row.dataset.rowKey;
            var variantPayload = {
                index: index,
                ibs_model: row.querySelector('.variant-ibs-model')?.value || '',
                sm_model: row.querySelector('.variant-sm-model')?.value || ''
            };

            var lockEl = row.querySelector('.product-control-low-lock');
            if (lockEl && lockEl.dataset.inherited === 'true') {
                variantPayload.low_warning = { inherit: true };
            } else {
                var lowVal = row.querySelector('.variant-low-warning')?.value;
                variantPayload.low_warning = { value: parseInt(lowVal || parentLowValue(), 10) };
            }

            var adj = pendingAdjustments[rowKey] || {};
            if (adj.rate) variantPayload.rate = adj.rate;
            if (adj.stock) variantPayload.ibs_stock = adj.stock;

            payload.variants.push(variantPayload);
        });

        return payload;
    }

    function updateSummary(summary) {
        if (!summary) return;
        var map = {
            warehouse_preview: summary.warehouse_preview,
            unique_ibs_models: summary.unique_ibs_models,
            health_ok: summary.health_ok,
            variable_products: summary.variable_products,
            variant_rows: summary.variant_rows
        };
        document.querySelectorAll('[data-summary-key]').forEach(function (el) {
            var key = el.getAttribute('data-summary-key');
            if (map[key] !== undefined) el.textContent = map[key];
        });
    }

    function updateRow(productIndex, product) {
        var group = document.getElementById('group-product-row-' + productIndex);
        if (!group) return;
        var row = document.getElementById('product-row-' + productIndex);
        if (!row) return;

        setCell(row, 'ibs_model', displayField(product.ibs_model));
        setCell(row, 'sm_model', displayField(product.sm_model));
        setCell(row, 'rate', formatRate(product.rate));
        setCell(row, 'ibs_stock', displayIbsStock(product.ibs_stock));

        var healthCell = row.querySelector('[data-cell="health"]');
        if (healthCell) healthCell.innerHTML = renderHealthBadge(product.health);

        (product.options || []).forEach(function (option, i) {
            var vRow = document.getElementById('product-row-' + productIndex + '-variant-' + i);
            if (!vRow) return;
            setCell(vRow, 'ibs_model', displayField(option.ibs_model));
            setCell(vRow, 'sm_model', displayField(option.sm_model));
            setCell(vRow, 'rate', formatRate(option.rate));
            setCell(vRow, 'ibs_stock', displayIbsStock(option.ibs_stock));
            var vHealth = vRow.querySelector('[data-cell="health"]');
            if (vHealth) vHealth.innerHTML = renderHealthBadge(option.health);
        });

        var historyBtn = group.querySelector('[data-control-open]');
        if (historyBtn) {
            var count = (activityByProduct[String(product.product_id || product.oc_product_id || '')] || []).length;
            historyBtn.textContent = count ? 'Edit (' + count + ')' : 'Edit';
        }
    }

    function setCell(row, key, value) {
        var cell = row.querySelector('[data-cell="' + key + '"]');
        if (cell) cell.textContent = value;
    }

    parentLowWarningInput.addEventListener('input', updateFormState);
    parentLowWarningInput.addEventListener('blur', updateFormState);

    document.getElementById('control-popover-cancel').addEventListener('click', closePopover);
    document.getElementById('control-popover-apply').addEventListener('click', applyPopover);
    document.getElementById('control-popover-stock-mode').addEventListener('change', function () {
        toggleStockReasonField();
        document.getElementById('control-popover-stock-error').classList.add('hidden');
    });

    document.addEventListener('click', function (e) {
        if (!popover.classList.contains('hidden') && !popover.contains(e.target) && !e.target.closest('[data-row-adjust]')) {
            closePopover();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!popover.classList.contains('hidden')) closePopover();
            else if (!modal.classList.contains('hidden')) closeModal();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (currentIndex === null || !isFormDirty()) return;

        errorEl.classList.add('hidden');
        saveBtn.disabled = true;

        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(buildPayload())
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    errorEl.textContent = result.data.message || 'Save failed.';
                    errorEl.classList.remove('hidden');
                    updateFormState();
                    return;
                }
                products[currentIndex] = result.data.product;
                var productId = String(result.data.product.product_id || result.data.product.oc_product_id || '');
                activityByProduct[productId] = result.data.activity || [];
                updateRow(currentIndex, result.data.product);
                updateSummary(result.data.summary);
                closeModal();
            })
            .catch(function () {
                errorEl.textContent = 'Save failed. Please try again.';
                errorEl.classList.remove('hidden');
                updateFormState();
            });
    });

    document.querySelectorAll('[data-close-control-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
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

    window.productMapOpenControl = openModal;
})();
</script>
@endpush
