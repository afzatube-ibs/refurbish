@extends('layouts.app')

@section('title', 'Suppliers — DropFlow SFM')
@section('page-title', 'Suppliers')
@section('page-subtitle', 'Manage supplier profiles and accounts')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Name</th>
                            <th class="text-left font-medium text-slate-600">Code</th>
                            <th class="text-left font-medium text-slate-600">Contact</th>
                            <th class="text-left font-medium text-slate-600">Status</th>
                            <th class="text-left font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($suppliers as $supplier)
                            <tr class="hover:bg-slate-50">
                                <td class="font-medium text-slate-900">{{ $supplier->name }}</td>
                                <td class="text-slate-600">{{ $supplier->code }}</td>
                                <td class="text-slate-600">
                                    @if ($supplier->contact_name)
                                        <div>{{ $supplier->contact_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $supplier->contact_email }}</div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button"
                                            onclick="editSupplier({{ json_encode($supplier) }})"
                                            class="text-sm text-slate-600 hover:text-slate-900 underline">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-slate-500 py-12">No suppliers configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 id="supplier-form-title" class="font-medium text-slate-900 mb-4">Add Supplier</h2>

            <form id="supplier-form" method="POST" action="{{ route('settings.suppliers.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="supplier-form-method" value="POST">

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="code" class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                    <input type="text" name="code" id="code" value="{{ old('code') }}" required
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="contact_name" class="block text-sm font-medium text-slate-700 mb-1">Contact Name</label>
                    <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name') }}"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="contact_phone" class="block text-sm font-medium text-slate-700 mb-1">Contact Phone</label>
                    <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone') }}"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="contact_email" class="block text-sm font-medium text-slate-700 mb-1">Contact Email</label>
                    <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email') }}"
                           class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" checked
                           class="rounded border-slate-300 text-slate-600 focus:ring-slate-500">
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Save supplier
                    </button>
                    <button type="button" id="supplier-form-reset" class="hidden rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Cancel edit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('supplier-form');
    const formTitle = document.getElementById('supplier-form-title');
    const formMethod = document.getElementById('supplier-form-method');
    const resetBtn = document.getElementById('supplier-form-reset');
    const storeUrl = @json(route('settings.suppliers.store'));

    function editSupplier(supplier) {
        form.action = @json(url('/settings/suppliers')) + '/' + supplier.id;
        formMethod.value = 'PUT';
        formTitle.textContent = 'Edit Supplier';
        resetBtn.classList.remove('hidden');
        document.getElementById('name').value = supplier.name;
        document.getElementById('code').value = supplier.code;
        document.getElementById('contact_name').value = supplier.contact_name || '';
        document.getElementById('contact_phone').value = supplier.contact_phone || '';
        document.getElementById('contact_email').value = supplier.contact_email || '';
        document.getElementById('is_active').checked = supplier.is_active;
    }

    resetBtn.addEventListener('click', () => {
        form.action = storeUrl;
        formMethod.value = 'POST';
        formTitle.textContent = 'Add Supplier';
        resetBtn.classList.add('hidden');
        form.reset();
        document.getElementById('is_active').checked = true;
    });
</script>
@endpush
