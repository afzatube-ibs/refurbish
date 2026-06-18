{{-- $items: [['label' => '...', 'value' => '...', 'tone' => 'default|accent|muted']] --}}
<div class="df-summary-bar">
    @foreach ($items as $item)
        <span class="df-summary-item df-summary-item--{{ $item['tone'] ?? 'default' }}">
            <span class="df-summary-item__label">{{ $item['label'] }}</span>
            <span class="df-summary-item__value">{{ $item['value'] }}</span>
        </span>
    @endforeach
</div>
