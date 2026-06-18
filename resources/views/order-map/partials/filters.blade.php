<form method="GET" action="{{ route('order-map.index') }}" class="df-filter-row">
    <div class="df-filter-group">
        <label for="order-map-status" class="df-filter-label">IBS Status</label>
        <select name="status" id="order-map-status" class="df-select" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach (\App\Enums\SfmOrderStatus::ibsWorkflowCases() as $status)
                <option value="{{ $status->value }}" @selected(($statusFilter ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
</form>
