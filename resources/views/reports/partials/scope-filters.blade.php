@if (auth()->user()->isAdmin())
    @php
        $selectedSupplierId = request('supplier_id', $defaultSupplierId ?? '');
        $selectedConnectionId = request('connection_id', $defaultConnectionId ?? '');
    @endphp
    @if ($singleSupplier ?? false)
        <input type="hidden" name="supplier_id" value="{{ $defaultSupplierId }}">
    @else
        <div class="df-filter-group">
            <label for="supplier_id" class="df-filter-label">Supplier</label>
            <select name="supplier_id" id="supplier_id" class="df-select">
                @foreach ($suppliers ?? [] as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if (isset($stores))
        @if ($singleStore ?? false)
            <input type="hidden" name="connection_id" value="{{ $defaultConnectionId }}">
        @else
            <div class="df-filter-group">
                <label for="connection_id" class="df-filter-label">Store</label>
                <select name="connection_id" id="connection_id" class="df-select">
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected((string) $selectedConnectionId === (string) $store->id)>
                            {{ parse_url($store->store_url, PHP_URL_HOST) ?: $store->store_url }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    @endif
@endif
