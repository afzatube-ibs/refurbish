<form method="GET" action="{{ url()->current() }}" class="mb-4 flex flex-wrap items-end gap-3">
    @if (auth()->user()->isAdmin())
        <div>
            <label for="supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
            <select name="supplier_id" id="supplier_id"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All suppliers</option>
                @foreach ($suppliers ?? [] as $supplier)
                    <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
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
    <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Filter
    </button>
</form>
