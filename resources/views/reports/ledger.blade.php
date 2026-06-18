@extends('layouts.app')



@section('title', 'Account Statement — DropFlow SFM')

@section('page-title', 'Account Statement')

@section('page-subtitle', 'Supplier ledger with running balance')



@section('content')

@include('reports.partials.filters')



<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div class="bg-white rounded-lg border border-slate-200 p-4">

        <p class="text-xs font-medium text-slate-500">Dispatched Cost</p>

        <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($summary['delivered_cost'] ?? 0, 2) }}</p>

    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-4">

        <p class="text-xs font-medium text-slate-500">Return Cost</p>

        <p class="mt-1 text-xl font-semibold text-orange-600">{{ number_format($summary['returned_cost'] ?? 0, 2) }}</p>

    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-4">

        <p class="text-xs font-medium text-slate-500">Total Paid</p>

        <p class="mt-1 text-xl font-semibold text-slate-700">{{ number_format($summary['total_paid'] ?? 0, 2) }}</p>

    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-4">

        <p class="text-xs font-medium text-slate-500">Current Balance</p>

        <div class="mt-1">
            @include('partials.balance-display', [
                'amount' => $balancePresentation['amount'] ?? ($summary['net_payable'] ?? 0),
                'meaning' => $balancePresentation['meaning'] ?? null,
                'toneClass' => $balancePresentation['tone_class'] ?? null,
                'amountClass' => 'text-xl font-semibold',
            ])
        </div>

    </div>

</div>



<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">

        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">

            <thead class="bg-slate-50">

                <tr>

                    <th class="text-left font-medium text-slate-600">Date</th>

                    <th class="text-left font-medium text-slate-600">Supplier</th>

                    <th class="text-left font-medium text-slate-600">Store</th>

                    <th class="text-left font-medium text-slate-600">Type</th>

                    <th class="text-left font-medium text-slate-600">Order</th>

                    <th class="text-right font-medium text-slate-600">Amount</th>

                    <th class="text-right font-medium text-slate-600">Balance</th>

                    <th class="text-left font-medium text-slate-600">Reference</th>

                    <th class="text-left font-medium text-slate-600">Notes</th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse ($rows ?? [] as $row)

                    @php $entry = $row['entry']; @endphp

                    <tr class="hover:bg-slate-50">

                        <td class="text-slate-900">{{ $entry->entry_date->format('M j, Y') }}</td>

                        <td class="text-slate-600">{{ $entry->supplier?->name }}</td>

                        <td class="text-slate-500 text-xs">{{ $entry->connection?->store_url ? parse_url($entry->connection->store_url, PHP_URL_HOST) : '—' }}</td>

                        <td class="text-slate-600">{{ $row['type_label'] }}</td>

                        <td class="text-slate-600 font-mono text-xs">{{ $entry->order?->source_order_id ?? '—' }}</td>

                        <td class="text-right font-medium {{ (float) $entry->amount < 0 ? 'text-orange-600' : 'text-slate-900' }}">

                            {{ number_format($entry->amount, 2) }}

                        </td>

                        <td class="text-right font-semibold text-emerald-700">{{ number_format($row['running_balance'], 2) }}</td>

                        <td class="text-slate-600 font-mono text-xs">
                            @if (!empty($row['batch_no']))
                                <a href="{{ route('settlements.show', $row['batch_id']) }}" class="text-slate-700 hover:text-slate-900 underline">{{ $row['batch_no'] }}</a>
                            @else
                                {{ $entry->reference ?? '—' }}
                            @endif
                        </td>

                        <td class="text-slate-500 text-xs">{{ $entry->notes ?? '—' }}</td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="9" class="text-center text-slate-500 py-12">No ledger entries for selected filters. Dispatch, returns, and settlements post here automatically.</td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection

