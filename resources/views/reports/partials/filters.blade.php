<form method="GET" action="{{ url()->current() }}" class="df-filter-row">
    @include('reports.partials.scope-filters')
    <div class="df-filter-group">
        <label for="from" class="df-filter-label">From</label>
        <input type="date" name="from" id="from" value="{{ request('from') }}" class="df-date">
    </div>
    <div class="df-filter-group">
        <label for="to" class="df-filter-label">To</label>
        <input type="date" name="to" id="to" value="{{ request('to') }}" class="df-date">
    </div>
    @if (request()->routeIs('reports.orders'))
        <div class="df-filter-group">
            <label for="sfm_status" class="df-filter-label">SFM Status</label>
            <select name="sfm_status" id="sfm_status" class="df-select">
                <option value="">All statuses</option>
                @foreach (\App\Enums\SfmOrderStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('sfm_status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (request()->routeIs('reports.returns'))
        <div class="df-filter-group">
            <label for="status" class="df-filter-label">Return Status</label>
            <select name="status" id="status" class="df-select">
                <option value="">Queue + Received</option>
                @foreach (\App\Enums\ReturnStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (request()->routeIs('reports.ledger') && isset($types))
        <div class="df-filter-group">
            <label for="type" class="df-filter-label">Type</label>
            <select name="type" id="type" class="df-select">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="df-filter-actions">
        <button type="submit" class="df-btn df-btn--secondary">Filter</button>
    </div>
</form>
