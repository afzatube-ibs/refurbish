@extends('layouts.app')

@section('title', 'Orders — DropFlow SFM')
@section('page-title', 'Orders')
@section('page-subtitle', auth()->user()->isAdmin() ? 'All supplier orders' : 'Your assigned orders')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap gap-2">
        @foreach ($statusCounts ?? [] as $status => $count)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-slate-200 px-3 py-1 text-xs">
                <x-status-badge :status="$status" />
                <span class="font-medium text-slate-700">{{ $count }}</span>
            </span>
        @endforeach
    </div>

    @if (auth()->user()->isAdmin())
        <form method="POST" action="{{ route('order-map.sync') }}">
            @csrf
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Sync Orders
            </button>
        </form>
    @endif
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order ID</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Customer</th>
                    <th class="text-left font-medium text-slate-600">Sale Amount</th>
                    <th class="text-left font-medium text-slate-600">LK Status</th>
                    <th class="text-left font-medium text-slate-600">SFM Status</th>
                    <th class="text-left font-medium text-slate-600">Created</th>
                    <th class="text-left font-medium text-slate-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900 whitespace-nowrap">#{{ $order->source_order_id }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $order->supplier?->name ?? '—' }}</td>
                        @endif
                        <td>
                            <div class="text-slate-900">{{ $order->customer_name }}</div>
                            <div class="text-xs text-slate-400">{{ $order->customer_phone }}</div>
                        </td>
                        <td class="text-slate-900">{{ number_format($order->sale_amount, 2) }}</td>
                        <td class="text-slate-600">{{ $order->current_oc_status }}</td>
                        <td><x-status-badge :status="$order->sfm_status" /></td>
                        <td class="text-slate-500 text-xs whitespace-nowrap">{{ $order->oc_created_at?->format('M j, Y') ?? $order->created_at->format('M j, Y') }}</td>
                        <td>
                            <a href="{{ route('order-map.show', $order) }}" class="text-sm text-slate-600 hover:text-slate-900 underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center text-slate-500 py-12">
                            No orders found.
                            @if (auth()->user()->isAdmin())
                                Sync orders from Lokkisona to import.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (isset($orders) && method_exists($orders, 'links'))
    <div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
