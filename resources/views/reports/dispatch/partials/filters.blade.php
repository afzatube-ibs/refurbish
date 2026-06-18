<form method="GET" action="{{ route('reports.dispatch') }}" class="mb-4 flex flex-wrap items-end gap-3">
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
        @if (isset($stores))
            <div>
                <label for="connection_id" class="block text-xs font-medium text-slate-600 mb-1">Store</label>
                <select name="connection_id" id="connection_id"
                        class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected(request('connection_id') == $store->id)>
                            {{ parse_url($store->store_url, PHP_URL_HOST) ?: $store->store_url }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    @endif
    <div>
        <label for="from" class="block text-xs font-medium text-slate-600 mb-1">From</label>
        <input type="date" name="from" id="from" value="{{ request('from', $from ?? '') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium text-slate-600 mb-1">To</label>
        <input type="date" name="to" id="to" value="{{ request('to', $to ?? '') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <div>
        <label for="courier" class="block text-xs font-medium text-slate-600 mb-1">Courier</label>
        <input type="text" name="courier" id="courier" value="{{ request('courier', $courier ?? '') }}"
               placeholder="Courier name"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <div>
        <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <input type="text" name="search" id="search" value="{{ request('search', $search ?? '') }}"
               placeholder="Order no or phone"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Filter
    </button>
</form>
