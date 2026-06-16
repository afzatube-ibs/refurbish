<form method="GET" action="{{ route('order-map.index') }}" class="order-map-filters">
    <label class="order-map-filter-label">
        IBS Status
        <select name="status" class="form-input form-input-compact" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach (\App\Enums\SfmOrderStatus::ibsWorkflowCases() as $status)
                <option value="{{ $status->value }}" @selected(($statusFilter ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </label>
</form>
