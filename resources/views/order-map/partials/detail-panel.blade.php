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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="order-map-list-card p-6">
                <h2 class="font-medium text-slate-900 mb-4">Customer</h2>

                @if ($canEdit ?? false)
                    <form method="POST" action="{{ route('order-map.update', $order) }}" class="space-y-4">
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
                        <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                    </form>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-500">Name</dt>
                            <dd class="font-medium text-slate-900 mt-1">{{ $order->customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Phone</dt>
                            <dd class="font-medium text-slate-900 mt-1">{{ $order->customer_phone }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Address</dt>
                            <dd class="font-medium text-slate-900 mt-1">{{ $order->customer_address }}</dd>
                        </div>
                        @if ($order->notes)
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Notes</dt>
                                <dd class="font-medium text-slate-900 mt-1 whitespace-pre-wrap">{{ $order->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </div>

            <div class="order-map-list-card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="font-medium text-slate-900">Product Lines</h2>
                </div>
                <div class="p-4">
                    @include('order-map.partials.product-card', [
                        'cards' => $queueRow['product_cards'] ?? [],
                        'hasUnmatched' => $queueRow['has_unmatched'] ?? false,
                    ])
                </div>
                <div class="px-6 py-3 border-t border-slate-200 text-sm flex justify-between">
                    <span class="text-slate-600">Supplier cost total</span>
                    <span class="font-semibold text-slate-900">{{ number_format($queueRow['total_cost'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            @if (! auth()->user()->isAdmin() && ($availableTransitions ?? []))
                <div class="order-map-list-card p-6">
                    <h2 class="font-medium text-slate-900 mb-4">IBS Actions</h2>
                    @include('order-map.partials.workflow-actions', [
                        'order' => $order,
                        'availableTransitions' => $availableTransitions,
                    ])
                </div>
            @endif

            <div class="order-map-list-card p-6 text-sm space-y-3">
                <div class="flex justify-between"><span class="text-slate-500">Total Qty</span><span>{{ $queueRow['total_qty'] ?? 0 }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Consignment</span><span>{{ $order->consignment_id ?: '—' }}</span></div>
                <a href="{{ route('order-map.print-invoice', $order) }}" class="btn btn-secondary btn-sm w-full text-center" target="_blank" rel="noopener">Print Invoice</a>
                @unless ($compactHeader ?? false)
                    <a href="{{ route('order-map.show', $order) }}" class="btn btn-ghost btn-sm w-full text-center">Open full page</a>
                @endunless
            </div>
        </div>
    </div>
</div>
