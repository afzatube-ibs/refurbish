@if (! empty($settlementHistory))
    <section class="order-map-list-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="order-map-detail-section-title">Settlement History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table order-map-detail-products-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="order-map-num">Amount</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settlementHistory as $line)
                        <tr>
                            <td>{{ $line['date'] }}</td>
                            <td>{{ $line['type_label'] }}</td>
                            <td class="order-map-num {{ ($line['amount'] ?? 0) < 0 ? 'text-orange-600' : '' }}">
                                {{ number_format($line['amount'], 2) }}
                            </td>
                            <td class="text-xs text-slate-600">{{ $line['reference'] ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ $line['notes'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
