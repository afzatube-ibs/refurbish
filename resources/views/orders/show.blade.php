@extends('layouts.app')

@section('title', 'Order #' . $order->source_order_id . ' — DropFlow SFM')
@section('page-title', 'Order #' . $order->source_order_id)
@section('page-subtitle', $order->customer_name)

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <x-status-badge :status="$order->sfm_status" />
    <span class="text-sm text-slate-500">OC: {{ $order->current_oc_status }}</span>
    @if ($order->courier_name)
        <span class="text-sm text-slate-500">· {{ $order->courier_name }} · {{ $order->consignment_id }}</span>
    @endif
    <a href="{{ route('order-map.index') }}" class="ml-auto text-sm text-slate-600 hover:text-slate-900 underline">← Back to orders</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Customer info --}}
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="font-medium text-slate-900 mb-4">Customer Information</h2>
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
                <div>
                    <dt class="text-slate-500">Sale Amount</dt>
                    <dd class="font-medium text-slate-900 mt-1">{{ number_format($order->sale_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Order Date</dt>
                    <dd class="font-medium text-slate-900 mt-1">{{ $order->oc_created_at?->format('M j, Y H:i') ?? $order->created_at->format('M j, Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Line items --}}
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Order Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Product</th>
                            <th class="text-left font-medium text-slate-600">Model</th>
                            <th class="text-left font-medium text-slate-600">Variant</th>
                            <th class="text-right font-medium text-slate-600">Qty</th>
                            <th class="text-right font-medium text-slate-600">Sale Price</th>
                            <th class="text-right font-medium text-slate-600">Cost Snapshot</th>
                            <th class="text-left font-medium text-slate-600">Item Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $item->product_name }}</td>
                                <td class="text-slate-600">{{ $item->model }}</td>
                                <td class="text-slate-600">{{ $item->variant_label ?? '—' }}</td>
                                <td class="text-right text-slate-900">{{ $item->quantity }}</td>
                                <td class="text-right text-slate-900">{{ number_format($item->sale_price, 2) }}</td>
                                <td class="text-right text-slate-600">
                                    {{ $item->supplier_product_cost_snapshot ? number_format($item->supplier_product_cost_snapshot, 2) : '—' }}
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ $item->item_status instanceof \App\Enums\OrderItemStatus ? $item->item_status->label() : ucfirst($item->item_status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="text-right font-medium text-slate-600">Total sale</td>
                            <td class="text-right font-semibold text-slate-900">{{ number_format($order->sale_amount, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Activity log --}}
        <div class="bg-white rounded-lg border border-slate-200">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Activity Log</h2>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($activityLogs ?? [] as $log)
                    <li class="px-6 py-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ $log->action }}</p>
                                @if ($log->metadata)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ json_encode($log->metadata) }}</p>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400 whitespace-nowrap">
                                {{ $log->created_at->format('M j, Y H:i') }}
                                @if ($log->user)
                                    · {{ $log->user->name }}
                                @endif
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-slate-500 text-sm">No activity recorded yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Workflow sidebar --}}
    <div class="space-y-6">
        @if (! auth()->user()->isAdmin() && ($availableTransitions ?? []))
            <div class="bg-white rounded-lg border border-slate-200 p-6">
                <h2 class="font-medium text-slate-900 mb-4">Supplier Actions</h2>
                <div class="space-y-3">
                    @if (in_array('accepted', $availableTransitions))
                        <form method="POST" action="{{ route('order-map.accept', $order) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Accept Order
                            </button>
                        </form>
                    @endif

                    @if (in_array('packed', $availableTransitions))
                        <form method="POST" action="{{ route('order-map.pack', $order) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                                Mark Packed
                            </button>
                        </form>
                    @endif

                    @if (in_array('dispatched', $availableTransitions))
                        <form method="POST" action="{{ route('order-map.dispatch', $order) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="courier" class="block text-xs font-medium text-slate-700 mb-1">Courier</label>
                                <input type="text" name="courier" id="courier" required
                                       class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                            <div>
                                <label for="consignment_id" class="block text-xs font-medium text-slate-700 mb-1">Consignment ID</label>
                                <input type="text" name="consignment_id" id="consignment_id" required
                                       class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                            <button type="submit" class="w-full rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                                Dispatch Order
                            </button>
                        </form>
                    @endif

                    @if (in_array('cancelled', $availableTransitions))
                        <form method="POST" action="{{ route('order-map.cancel', $order) }}"
                              onsubmit="return confirm('Cancel this order?');">
                            @csrf
                            <button type="submit" class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                Cancel Order
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm">
            <h3 class="font-medium text-slate-900 mb-3">Order Details</h3>
            <dl class="space-y-2 text-slate-600">
                @if (auth()->user()->isAdmin())
                    <div class="flex justify-between">
                        <dt>Supplier</dt>
                        <dd class="font-medium text-slate-900">{{ $order->supplier?->name }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt>SFM Status</dt>
                    <dd><x-status-badge :status="$order->sfm_status" /></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Courier Status</dt>
                    <dd>{{ $order->courier_status ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
