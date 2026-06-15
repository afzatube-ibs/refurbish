@extends('layouts.app')

@section('title', 'Returns — DropFlow SFM')
@section('page-title', 'Returns')
@section('page-subtitle', 'Return pending and confirmed returns')

@section('content')
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Status</th>
                    <th class="text-right font-medium text-slate-600">Items</th>
                    <th class="text-right font-medium text-slate-600">Return Cost</th>
                    <th class="text-left font-medium text-slate-600">Received Date</th>
                    <th class="text-left font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($returns as $return)
                    @php
                        $statusValue = $return->return_status instanceof \App\Enums\ReturnStatus
                            ? $return->return_status->value
                            : $return->return_status;
                        $statusLabel = $return->return_status instanceof \App\Enums\ReturnStatus
                            ? $return->return_status->label()
                            : ucfirst($statusValue);
                        $returnCost = $return->return_items_sum ?? $return->returnItems?->sum(fn ($i) => $i->quantity * $i->supplier_cost_snapshot) ?? 0;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td>
                            <a href="{{ route('orders.show', $return->order) }}" class="font-medium text-slate-900 hover:underline">
                                #{{ $return->order?->source_order_id }}
                            </a>
                        </td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $return->supplier?->name }}</td>
                        @endif
                        <td>
                            @if ($statusValue === 'confirmed')
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ $statusLabel }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">{{ $statusLabel }}</span>
                            @endif
                        </td>
                        <td class="text-right text-slate-900">{{ $return->items_count ?? $return->returnItems?->count() ?? 0 }}</td>
                        <td class="text-right font-medium text-slate-900">{{ number_format($returnCost, 2) }}</td>
                        <td class="text-slate-600">{{ $return->received_date?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            @if (! auth()->user()->isAdmin() && $statusValue === 'pending')
                                <form method="POST" action="{{ route('returns.confirm', $return) }}"
                                      onsubmit="return confirm('Confirm return received? This affects payable calculation.');"
                                      class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                                        Confirm Received
                                    </button>
                                </form>
                            @elseif ($statusValue === 'confirmed')
                                <span class="text-xs text-slate-500">
                                    {{ $return->confirmedBy?->name ?? 'Confirmed' }}
                                    @if ($return->confirmed_at)
                                        · {{ $return->confirmed_at->format('M j, Y') }}
                                    @endif
                                </span>
                            @else
                                <span class="text-xs text-slate-400">Awaiting supplier</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 7 : 6 }}" class="text-center text-slate-500 py-12">No returns found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (isset($returns) && method_exists($returns, 'links'))
    <div class="mt-4">{{ $returns->links() }}</div>
@endif
@endsection
