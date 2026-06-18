<form method="GET" action="{{ url()->current() }}" class="mb-4 flex flex-wrap items-end gap-3">
    @include('reports.partials.scope-filters')
    <div>
        <label for="from" class="block text-xs font-medium text-slate-600 mb-1">From</label>
        <input type="date" name="from" id="from" value="{{ request('from') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium text-slate-600 mb-1">To</label>
        <input type="date" name="to" id="to" value="{{ request('to') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    @if (request()->routeIs('reports.orders'))
        <div>
            <label for="sfm_status" class="block text-xs font-medium text-slate-600 mb-1">SFM Status</label>
            <select name="sfm_status" id="sfm_status"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All statuses</option>
                @foreach (\App\Enums\SfmOrderStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('sfm_status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (request()->routeIs('reports.returns'))
        <div>
            <label for="status" class="block text-xs font-medium text-slate-600 mb-1">Return Status</label>
            <select name="status" id="status"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Queue + Received</option>
                @foreach (\App\Enums\ReturnStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (request()->routeIs('reports.ledger') && isset($types))
        <div>
            <label for="type" class="block text-xs font-medium text-slate-600 mb-1">Type</label>
            <select name="type" id="type"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Filter
    </button>
</form>
