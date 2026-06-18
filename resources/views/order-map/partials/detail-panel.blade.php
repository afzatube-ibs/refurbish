@php
    use App\Enums\SfmOrderStatus;

    $status = $order->sfm_status ?? SfmOrderStatus::New;
    $isManualOrder = str_starts_with((string) $order->source_order_id, 'MAN-');
    $orderSource = $queueRow['source_label'] ?? ($isManualOrder ? 'Lokkisona Manual' : 'Lokkisona');
    $showSupplierActions = auth()->user()->isSupplier();
@endphp

<div class="order-map-detail-panel">
    @if ($compactHeader ?? false)
        <div class="order-map-detail-header mb-4 flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Order #{{ $order->source_order_id }}</h2>
            <x-status-badge :status="$order->sfm_status" />
            @if ($queueRow['has_unmatched'] ?? false)
                <span class="order-map-unmatched-badge">Unmatched products</span>
            @endif
        </div>
    @endif

    <div class="order-map-detail-sections space-y-4">
        <section class="order-map-list-card p-5">
            <h3 class="order-map-detail-section-title">Customer</h3>
            <dl class="order-map-detail-facts">
                <div>
                    <dt>Name</dt>
                    <dd>{{ $order->customer_name }}</dd>
                </div>
                <div>
                    <dt>Phone</dt>
                    <dd>{{ $order->customer_phone }}</dd>
                </div>
                @if (filled($order->customer_address))
                    <div class="order-map-detail-fact-wide">
                        <dt>Address</dt>
                        <dd>{{ $order->customer_address }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Source</dt>
                    <dd>{{ $orderSource }}</dd>
                </div>
            </dl>
        </section>

        <section class="order-map-list-card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="order-map-detail-section-title">Products</h3>
            </div>
            <div class="order-map-detail-products-wrap">
                <table class="data-table order-map-detail-products-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Model</th>
                            <th>Option</th>
                            <th class="order-map-num">Qty</th>
                            <th class="order-map-num">Supplier cost</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->items as $item)
                            @php
                                $option = trim((string) ($item->option_value ?? ''));
                                if ($option === '' && $item->variant_label) {
                                    $option = (string) $item->variant_label;
                                }
                                $matchLabel = $isManualOrder
                                    ? 'Manual item'
                                    : ($item->is_unmatched ? 'Unmatched' : 'Matched');
                                $matchClass = match ($matchLabel) {
                                    'Unmatched' => 'order-map-match--unmatched',
                                    'Manual item' => 'order-map-match--manual',
                                    default => 'order-map-match--matched',
                                };
                            @endphp
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td class="font-mono text-xs">{{ $item->model ?: '—' }}</td>
                                <td>{{ $option !== '' ? $option : '—' }}</td>
                                <td class="order-map-num">{{ (int) $item->quantity }}</td>
                                <td class="order-map-num">
                                    {{ $item->supplier_product_cost_snapshot !== null ? number_format((float) $item->supplier_product_cost_snapshot, 2) : '—' }}
                                </td>
                                <td><span class="order-map-match-badge {{ $matchClass }}">{{ $matchLabel }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="order-map-empty">No product lines.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-200 text-sm flex justify-between">
                <span class="text-slate-600">Supplier cost total</span>
                <span class="font-semibold text-slate-900">{{ number_format($queueRow['total_cost'] ?? 0, 2) }}</span>
            </div>
        </section>

        <section class="order-map-list-card p-5">
            <h3 class="order-map-detail-section-title">Fulfillment</h3>
            <dl class="order-map-detail-facts">
                <div>
                    <dt>IBS status</dt>
                    <dd><x-status-badge :status="$order->sfm_status" /></dd>
                </div>
                <div>
                    <dt>Consignment ID</dt>
                    <dd>{{ $order->consignment_id ?: '—' }}</dd>
                </div>
                @if (filled($order->courier_name))
                    <div>
                        <dt>Courier</dt>
                        <dd>{{ $order->courier_name }}</dd>
                    </div>
                @endif
                @if (filled($order->notes))
                    <div class="order-map-detail-fact-wide">
                        <dt>Notes</dt>
                        <dd class="whitespace-pre-wrap">{{ $order->notes }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        @if ($canEdit ?? false)
            <section class="order-map-list-card p-5" id="order-edit-section-{{ $order->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="order-map-detail-section-title mb-0">Edit Order</h3>
                    <button type="button"
                            class="btn btn-secondary btn-sm"
                            data-order-edit-toggle
                            data-order-edit-target="order-edit-form-{{ $order->id }}">
                        Edit Order
                    </button>
                </div>
                <form method="POST"
                      action="{{ route('order-map.update', $order) }}"
                      id="order-edit-form-{{ $order->id }}"
                      class="order-map-edit-form space-y-4"
                      data-order-edit-form
                      hidden>
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="order-customer-name-{{ $order->id }}" class="block text-sm text-slate-500 mb-1">Name</label>
                        <input type="text" name="customer_name" id="order-customer-name-{{ $order->id }}"
                               value="{{ old('customer_name', $order->customer_name) }}" class="form-input w-full" required>
                    </div>
                    <div>
                        <label for="order-customer-phone-{{ $order->id }}" class="block text-sm text-slate-500 mb-1">Phone</label>
                        <input type="text" name="customer_phone" id="order-customer-phone-{{ $order->id }}"
                               value="{{ old('customer_phone', $order->customer_phone) }}" class="form-input w-full" required>
                    </div>
                    <div>
                        <label for="order-customer-address-{{ $order->id }}" class="block text-sm text-slate-500 mb-1">Address</label>
                        <textarea name="customer_address" id="order-customer-address-{{ $order->id }}" rows="3"
                                  class="form-input w-full" required>{{ old('customer_address', $order->customer_address) }}</textarea>
                    </div>
                    <div>
                        <label for="order-notes-{{ $order->id }}" class="block text-sm text-slate-500 mb-1">Notes</label>
                        <textarea name="notes" id="order-notes-{{ $order->id }}" rows="3"
                                  class="form-input w-full">{{ old('notes', $order->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </form>
            </section>
        @elseif ($showSupplierActions)
            <p class="order-map-edit-locked">Editing locked for this status.</p>
        @endif

        @if ($showSupplierActions)
            <section class="order-map-list-card p-5">
                <h3 class="order-map-detail-section-title">Actions</h3>
                @include('order-map.partials.workflow-actions', [
                    'order' => $order,
                    'availableTransitions' => $availableTransitions ?? [],
                    'status' => $status,
                ])
            </section>
        @endif

        @include('order-map.partials.settlement-history', ['settlementHistory' => $settlementHistory ?? []])

        <div class="order-map-detail-footer-actions">
            <a href="{{ route('order-map.print-invoice', $order) }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Print Invoice</a>
            @unless ($compactHeader ?? false)
                <a href="{{ route('order-map.index') }}" class="btn btn-ghost btn-sm">Back to queue</a>
            @endunless
        </div>
    </div>
</div>
