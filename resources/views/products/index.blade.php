@extends('layouts.app')

@section('title', 'Products — DropFlow SFM')
@section('page-title', 'Products')
@section('page-subtitle', 'Supplier product catalog synced from OpenCart')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="POST" action="{{ route('product-map.sync') }}">
        @csrf
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Sync New Products
        </button>
    </form>

    <form method="POST" action="{{ route('product-map.refresh') }}">
        @csrf
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Refresh Stock &amp; Names
        </button>
    </form>

    @if ($hasMore ?? false)
        <form method="POST" action="{{ route('product-map.sync') }}">
            @csrf
            <input type="hidden" name="continue" value="1">
            <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Continue Sync (page {{ $syncPage ?? 1 }})
            </button>
        </form>
        <span class="text-sm text-amber-700">More products available on OpenCart.</span>
    @endif
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Product</th>
                    <th class="text-left font-medium text-slate-600">Model</th>
                    <th class="text-left font-medium text-slate-600">OC Stock</th>
                    <th class="text-left font-medium text-slate-600">Supplier Cost</th>
                    <th class="text-left font-medium text-slate-600">Supplier Model</th>
                    <th class="text-left font-medium text-slate-600">Supplier Stock</th>
                    <th class="text-left font-medium text-slate-600">Low Warning</th>
                    <th class="text-left font-medium text-slate-600">Last Synced</th>
                    <th class="text-left font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-slate-50 {{ ($product->low_warning && $product->stock <= $product->low_warning) ? 'bg-amber-50' : '' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                @if ($product->image)
                                    <img src="{{ $product->image }}" alt="" class="h-10 w-10 rounded object-cover bg-slate-100">
                                @endif
                                <div>
                                    <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                    <p class="text-xs text-slate-400">ID: {{ $product->source_product_id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="text-slate-600">{{ $product->model }}</td>
                        <td class="text-slate-900 font-medium">{{ $product->stock }}</td>
                        <td class="text-slate-900">{{ number_format($product->supplier_cost, 2) }}</td>
                        <td class="text-slate-600">{{ $product->supplier_model ?? '—' }}</td>
                        <td class="text-slate-600">{{ $product->supplier_stock ?? '—' }}</td>
                        <td class="text-slate-600">{{ $product->low_warning ?? '—' }}</td>
                        <td class="text-slate-500 text-xs whitespace-nowrap">{{ $product->last_synced_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td>
                            <button type="button"
                                    onclick="openProductModal({{ json_encode([
                                        'id' => $product->id,
                                        'name' => $product->name,
                                        'supplier_cost' => $product->supplier_cost,
                                        'supplier_model' => $product->supplier_model,
                                        'supplier_stock' => $product->supplier_stock,
                                        'low_warning' => $product->low_warning,
                                    ]) }})"
                                    class="text-sm text-slate-600 hover:text-slate-900 underline">
                                Edit supplier fields
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-slate-500 py-12">
                            No products synced yet. Run Sync New Products to import from OpenCart.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (isset($products) && method_exists($products, 'links'))
    <div class="mt-4">{{ $products->links() }}</div>
@endif

{{-- Edit supplier fields modal --}}
<div id="product-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="fixed inset-0 bg-slate-900/50" onclick="closeProductModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md relative">
            <form id="product-edit-form" method="POST" action="" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                <h3 id="product-modal-title" class="text-lg font-medium text-slate-900">Edit Supplier Fields</h3>

                <div>
                    <label for="supplier_cost" class="block text-sm font-medium text-slate-700 mb-1">Supplier Cost</label>
                    <input type="number" step="0.01" min="0" name="supplier_cost" id="supplier_cost" required
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="supplier_model" class="block text-sm font-medium text-slate-700 mb-1">Supplier Model</label>
                    <input type="text" name="supplier_model" id="supplier_model"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="supplier_stock" class="block text-sm font-medium text-slate-700 mb-1">Supplier Stock Override</label>
                    <input type="number" min="0" name="supplier_stock" id="supplier_stock"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="low_warning" class="block text-sm font-medium text-slate-700 mb-1">Low Stock Warning</label>
                    <input type="number" min="0" name="low_warning" id="low_warning"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeProductModal()"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const modal = document.getElementById('product-modal');
    const form = document.getElementById('product-edit-form');
    const baseUrl = @json(url('/products'));

    function openProductModal(product) {
        form.action = baseUrl + '/' + product.id;
        document.getElementById('product-modal-title').textContent = 'Edit: ' + product.name;
        document.getElementById('supplier_cost').value = product.supplier_cost;
        document.getElementById('supplier_model').value = product.supplier_model || '';
        document.getElementById('supplier_stock').value = product.supplier_stock ?? '';
        document.getElementById('low_warning').value = product.low_warning ?? '';
        modal.classList.remove('hidden');
    }

    function closeProductModal() {
        modal.classList.add('hidden');
    }
</script>
@endpush
