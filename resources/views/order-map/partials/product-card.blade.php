<div class="order-map-cards">
    @foreach ($cards as $card)
        <div class="order-map-card @if ($card['unmatched']) order-map-card--unmatched @endif">
            <div class="order-map-card-head">
                <span class="order-map-card-name">{{ $card['name'] }}</span>
                @if ($card['unmatched'])
                    <span class="order-map-unmatched-badge" title="No Product Control match">Unmatched</span>
                @endif
            </div>
            <div class="order-map-card-meta">
                <span class="order-map-card-model">{{ $card['model'] }}</span>
                @if ($card['option'])
                    <span class="order-map-card-option">{{ $card['option'] }}</span>
                @endif
            </div>
            <div class="order-map-card-foot">
                <span>Qty {{ $card['qty'] }}</span>
                @if ($card['cost'] !== null)
                    <span class="order-map-card-cost">{{ number_format((float) $card['cost'], 2) }}</span>
                @endif
            </div>
        </div>
    @endforeach
    @if ($hasUnmatched && $cards === [])
        <span class="order-map-unmatched-badge">Unmatched</span>
    @endif
</div>
