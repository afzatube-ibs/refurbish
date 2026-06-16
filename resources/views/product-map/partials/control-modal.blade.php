<div class="modal-overlay" id="productControlCenterModal" hidden aria-hidden="true">
    <div class="modal-panel modal-panel-product-control pcc-v202-panel" role="dialog" aria-labelledby="pccModalTitle" aria-modal="true">
        <div class="pcc-v202-header">
            <h2 class="pcc-modal-title" id="pccModalTitle">Product Control</h2>
            <button type="button" class="modal-close" data-close-control-modal aria-label="Close">&times;</button>
        </div>

        <p class="pcc-modal-error" id="pccModalError" hidden role="alert"></p>

        <div class="pcc-tabs pcc-v202-tabs" role="tablist">
            <button type="button" class="pcc-tab is-active" data-pcc-tab="details" role="tab" aria-selected="true">Product Details</button>
            <button type="button" class="pcc-tab" data-pcc-tab="history" role="tab" aria-selected="false">Rate / Stock History</button>
        </div>

        <form id="productControlCenterForm" class="pcc-v202-form">
            <input type="hidden" id="control-product-index" value="">

            <div class="pcc-tab-panel is-active pcc-v202-tab-panel" data-pcc-panel="details">
                <div class="pcc-v202-workspace" id="pccWorkspace">
                    <div class="pcc-v202-top-split">
                        <section class="pcc-v202-snapshot" aria-label="Product information">
                            <div class="pcc-v202-snapshot-image" id="pccMainImageWrap">
                                <div class="pcc-image-placeholder-card pcc-v202-img-placeholder" id="pccImagePlaceholder"><span>No image</span></div>
                                <img src="" alt="" class="pcc-product-image pcc-v202-product-image" id="pccProductImage" hidden>
                            </div>
                            <div class="pcc-v202-snapshot-body">
                                <h3 class="pcc-v202-section-title">Product Information</h3>
                                <dl class="pcc-v202-facts">
                                    <div class="pcc-v202-fact"><dt>OC Product ID</dt><dd id="pccProductIdDisplay">—</dd></div>
                                    <div class="pcc-v202-fact"><dt>OC Model</dt><dd id="pccMainModel">—</dd></div>
                                    <div class="pcc-v202-fact"><dt>Type</dt><dd id="pccProductType">—</dd></div>
                                </dl>
                            </div>
                        </section>

                        <section class="pcc-v202-supplier" id="pccVendorMappingCard" aria-label="Supplier control fields">
                            <h3 class="pcc-v202-section-title">Supplier Control</h3>
                            <div class="pcc-v202-supplier-fields" id="pccSupplierFields">
                                <div class="pcc-v202-field-row" data-supplier-field="ibs_model">
                                    <span class="pcc-v202-field-label">IBS Model</span>
                                    <span class="pcc-v202-field-value pcc-dblclick-edit" data-field="ibs_model" data-scope="parent" tabindex="0" title="Double-click to edit">—</span>
                                </div>
                                <div class="pcc-v202-field-row" data-supplier-field="sm_model">
                                    <span class="pcc-v202-field-label">SM Model</span>
                                    <span class="pcc-v202-field-value pcc-dblclick-edit" data-field="sm_model" data-scope="parent" tabindex="0" title="Double-click to edit">—</span>
                                </div>
                                <div class="pcc-v202-field-row" data-supplier-field="product_category">
                                    <span class="pcc-v202-field-label">Product Category</span>
                                    <span class="pcc-v202-field-value pcc-dblclick-edit pcc-category-display" data-field="product_category" data-scope="parent" data-input-type="category" tabindex="0" title="Double-click to edit">—</span>
                                </div>
                                <div class="pcc-v202-field-row" data-supplier-field="rate">
                                    <span class="pcc-v202-field-label">Current Rate</span>
                                    <span class="pcc-v202-field-value pcc-dblclick-edit" data-field="rate" data-scope="parent" data-input-type="decimal" tabindex="0" title="Double-click to edit">—</span>
                                    <button type="button" class="pcc-v202-adjust-mini pcc-v202-adjust-trigger" data-adjust-scope="parent" data-adjust-mode="price" data-adjust-index="" title="Adjust Rate" aria-label="Adjust Rate">Rate</button>
                                </div>
                                <div class="pcc-v202-field-row" data-supplier-field="low_warning">
                                    <span class="pcc-v202-field-label">Low Warning</span>
                                    <span class="pcc-v202-field-value pcc-dblclick-edit" data-field="low_warning" data-scope="parent" data-input-type="integer" tabindex="0" title="Double-click to edit"></span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="pcc-v202-options" id="pccVariantSection" hidden>
                        <div class="pcc-v202-options-head">
                            <h3 class="pcc-v202-options-title">Option Rows</h3>
                            <span class="pcc-v202-options-hint">Double-click cell to edit</span>
                        </div>
                        <div class="pcc-v202-table-wrap">
                            <table class="data-table pcc-variant-lines-table pcc-v202-table pcc-dropflow-table" id="pccVariantLinesTable">
                                <thead>
                                    <tr>
                                        <th class="pcc-vcol-image">Image</th>
                                        <th class="pcc-vcol-model">OC Model</th>
                                        <th class="pcc-vcol-vendor">IBS Model</th>
                                        <th class="pcc-vcol-sm">SM Model</th>
                                        <th class="pcc-vcol-cost">Rate</th>
                                        <th class="pcc-vcol-vstock">IBS Stock</th>
                                        <th class="pcc-vcol-warn">Low Warning</th>
                                    </tr>
                                </thead>
                                <tbody id="pccVariantLinesBody"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="pcc-v202-options" id="pccSimpleTableSection" hidden>
                        <div class="pcc-v202-options-head">
                            <h3 class="pcc-v202-options-title">Product Row</h3>
                            <span class="pcc-v202-options-hint">Double-click cell to edit</span>
                        </div>
                        <div class="pcc-v202-table-wrap">
                            <table class="data-table pcc-variant-lines-table pcc-v202-table pcc-dropflow-table" id="pccSimpleLinesTable">
                                <thead>
                                    <tr>
                                        <th class="pcc-vcol-image">Image</th>
                                        <th class="pcc-vcol-model">OC Model</th>
                                        <th class="pcc-vcol-vendor">IBS Model</th>
                                        <th class="pcc-vcol-sm">SM Model</th>
                                        <th class="pcc-vcol-category">Product Category</th>
                                        <th class="pcc-vcol-cost">Rate</th>
                                        <th class="pcc-vcol-vstock">IBS Stock</th>
                                        <th class="pcc-vcol-warn">Low Warning</th>
                                    </tr>
                                </thead>
                                <tbody id="pccSimpleLinesBody"></tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="pcc-v202-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="pccCancelBtn" hidden>Cancel Changes</button>
                    <button type="submit" class="btn btn-primary" id="pccSaveBtn" disabled>Save All Changes</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-close-control-modal>Close</button>
                </div>
            </div>

            <div class="pcc-tab-panel pcc-v202-tab-panel" data-pcc-panel="history" hidden>
                <div class="pcc-v202-history-wrap">
                    <table class="data-table pcc-history-table">
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>Product / Variant Model</th>
                                <th>Type</th>
                                <th>Old</th>
                                <th>New</th>
                                <th>Difference</th>
                                <th>Reason</th>
                                <th>Note</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody id="pccHistoryRows">
                            <tr><td colspan="9" class="pcc-muted-cell">Open a product to view history.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pcc-v202-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-close-control-modal>Close</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="pcc-adjust-popover pcc-v202-adjust-modal" id="pccAdjustPopover" hidden>
    <div class="pcc-adjust-popover-inner">
        <p class="pcc-adjust-title" id="pccAdjustTitle">Adjust Rate</p>
        <label class="pcc-field pcc-field-compact" id="pccAdjustCurrentWrap">Current Value
            <input type="text" id="pccAdjustCurrent" class="form-input pcc-adjust-current-input" readonly disabled aria-readonly="true">
        </label>
        <label class="pcc-field pcc-field-compact" id="pccAdjustChangeTypeWrap">Change Type
            <select id="pccAdjustChangeType" class="form-input">
                <option value="increase">Increase</option>
                <option value="decrease">Decrease</option>
            </select>
        </label>
        <label class="pcc-field pcc-field-compact" id="pccAdjustMethodWrap">Method
            <select id="pccAdjustMethod" class="form-input">
                <option value="fixed">Fixed Amount</option>
                <option value="percent">Percentage</option>
            </select>
        </label>
        <label class="pcc-field pcc-field-compact" id="pccAdjustAmountWrap">
            <span class="pcc-label-inline"><span id="pccAdjustAmountLabel">Amount</span><span class="pcc-required">*</span></span>
            <input type="number" id="pccAdjustAmount" class="form-input" min="0" step="any" placeholder="Enter amount" required>
        </label>
        <label class="pcc-field pcc-field-compact" id="pccAdjustReasonWrap">
            <span class="pcc-label-inline">Reason<span class="pcc-required">*</span></span>
            <select id="pccAdjustReason" class="form-input" required>
                <option value="">Select reason</option>
                @foreach ($stockReasons as $reason)
                    <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
            </select>
        </label>
        <label class="pcc-field pcc-field-compact" id="pccAdjustNoteWrap">Note (optional)
            <input type="text" id="pccAdjustNote" class="form-input" placeholder="Optional note">
        </label>
        <p class="pcc-adjust-preview" id="pccAdjustPreview"></p>
        <div class="pcc-adjust-actions">
            <button type="button" class="btn btn-primary btn-sm" id="pccAdjustApply">Apply</button>
            <button type="button" class="btn btn-ghost btn-sm" id="pccAdjustCancel">Cancel</button>
        </div>
    </div>
</div>
