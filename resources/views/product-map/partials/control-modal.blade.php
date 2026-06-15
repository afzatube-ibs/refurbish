<div id="product-control-modal" class="product-control-modal hidden" aria-hidden="true">
    <div class="product-control-modal__backdrop" data-close-control-modal></div>
    <div class="product-control-modal__panel" role="dialog" aria-modal="true" aria-labelledby="product-control-title">
        <div class="product-control-modal__header">
            <div>
                <h3 id="product-control-title" class="text-lg font-semibold text-slate-900">Product Control</h3>
                <p class="text-sm text-slate-500">Local only — double-click text fields to edit.</p>
            </div>
            <button type="button" class="product-control-modal__close" data-close-control-modal aria-label="Close">&times;</button>
        </div>

        <form id="product-control-form" class="product-control-modal__form">
            <input type="hidden" id="control-product-index" name="product_index" value="">

            <div class="product-control-modal__scroll">
                <section class="product-control-summary">
                    <div id="control-product-image" class="product-control-thumb-wrap">—</div>
                    <div class="product-control-summary-fields">
                        <div class="product-control-summary-readonly">
                            <span class="product-control-label">Product ID</span>
                            <span id="control-product-id" class="product-control-value font-mono text-xs">—</span>
                        </div>
                        <div class="product-control-summary-readonly">
                            <span class="product-control-label">Model</span>
                            <span id="control-product-model" class="product-control-value font-mono text-xs">—</span>
                        </div>
                        <div class="product-control-field-wrap" data-field-key="parent.ibs_model">
                            <label class="product-control-label" for="control-parent-ibs-model">IBS Model</label>
                            <input type="text" id="control-parent-ibs-model" class="product-control-input control-lockable" autocomplete="off" readonly>
                        </div>
                        <div class="product-control-field-wrap" data-field-key="parent.sm_model">
                            <label class="product-control-label" for="control-parent-sm-model">SM Model</label>
                            <input type="text" id="control-parent-sm-model" class="product-control-input control-lockable" autocomplete="off" readonly>
                        </div>
                        <div class="product-control-field-wrap product-control-field-wrap--narrow" data-field-key="parent.low_warning">
                            <label class="product-control-label" for="control-parent-low-warning">Low Warning</label>
                            <input type="number" min="0" step="1" id="control-parent-low-warning" class="product-control-input control-lockable" readonly>
                        </div>
                    </div>
                </section>

                <section id="control-rows-section" class="product-control-table-section">
                    <h4 id="control-rows-title" class="product-control-section__title">Variants</h4>
                    <div class="product-control-table-wrap">
                        <table class="product-control-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Model</th>
                                    <th>IBS Model</th>
                                    <th>SM Model</th>
                                    <th>Rate</th>
                                    <th>IBS Stock</th>
                                    <th>Low Warning</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="control-rows-tbody"></tbody>
                        </table>
                    </div>
                </section>

                <details class="product-control-activity-details">
                    <summary class="product-control-section__title product-control-activity-summary">Activity History</summary>
                    <div class="product-control-activity-panels">
                        <div class="product-control-activity-panel">
                            <h5 class="product-control-activity-heading">Parent Activity</h5>
                            <div id="control-parent-activity" class="product-control-activity-list">
                                <p class="product-control-activity-empty">No parent changes yet.</p>
                            </div>
                        </div>
                        <div class="product-control-activity-panel">
                            <h5 class="product-control-activity-heading">Variant Activity</h5>
                            <div id="control-variant-activity" class="product-control-activity-list">
                                <p class="product-control-activity-empty">No variant changes yet.</p>
                            </div>
                        </div>
                    </div>
                </details>

                <p id="control-form-error" class="product-control-error hidden"></p>
            </div>

            <div class="product-control-modal__footer">
                <button type="button" class="product-control-btn product-control-btn--secondary" data-close-control-modal>Cancel</button>
                <button type="submit" id="control-save-btn" class="product-control-btn product-control-btn--primary" disabled>Save locally</button>
            </div>
        </form>

        <div id="control-adjust-popover" class="product-control-popover hidden" role="dialog" aria-modal="true" aria-labelledby="control-popover-title">
            <div class="product-control-popover__inner">
                <h4 id="control-popover-title" class="product-control-popover__title">Adjust Rate &amp; Stock</h4>
                <input type="hidden" id="control-popover-row-key" value="">

                <div class="product-control-popover__section">
                    <h5 class="product-control-popover__section-title">Rate</h5>
                    <p class="product-control-popover__current">Current: <strong id="control-popover-rate-current">—</strong></p>
                    <div class="product-control-popover__field">
                        <label class="product-control-label" for="control-popover-rate-mode">Change</label>
                        <select id="control-popover-rate-mode" class="product-control-select product-control-select--compact">
                            <option value="">No change</option>
                            <option value="set">Set</option>
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                    </div>
                    <div class="product-control-popover__field">
                        <label class="product-control-label" for="control-popover-rate-amount">Amount</label>
                        <input type="number" step="any" min="0" id="control-popover-rate-amount" class="product-control-input product-control-input--compact" placeholder="Amount">
                    </div>
                    <span id="control-popover-rate-error" class="product-control-popover-error hidden"></span>
                </div>

                <div class="product-control-popover__section">
                    <h5 class="product-control-popover__section-title">IBS Stock</h5>
                    <p class="product-control-popover__current">Current: <strong id="control-popover-stock-current">—</strong></p>
                    <div class="product-control-popover__field">
                        <label class="product-control-label" for="control-popover-stock-mode">Change</label>
                        <select id="control-popover-stock-mode" class="product-control-select product-control-select--compact">
                            <option value="">No change</option>
                            <option value="set">Set</option>
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                    </div>
                    <div class="product-control-popover__field">
                        <label class="product-control-label" for="control-popover-stock-amount">Amount</label>
                        <input type="number" step="1" min="0" id="control-popover-stock-amount" class="product-control-input product-control-input--compact" placeholder="Amount">
                    </div>
                    <div id="control-popover-reason-wrap" class="product-control-popover__field hidden">
                        <label class="product-control-label" for="control-popover-stock-reason">Reason</label>
                        <select id="control-popover-stock-reason" class="product-control-select product-control-select--compact">
                            <option value="">Select reason</option>
                            @foreach ($stockReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span id="control-popover-stock-error" class="product-control-popover-error hidden"></span>
                </div>

                <div class="product-control-popover__actions">
                    <button type="button" class="product-control-btn product-control-btn--secondary" id="control-popover-cancel">Cancel</button>
                    <button type="button" class="product-control-btn product-control-btn--primary" id="control-popover-apply">Apply</button>
                </div>
            </div>
        </div>
    </div>
</div>
