<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSupplierRequest;
use App\Http\Requests\Settings\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('settings.suppliers.index', [
            'suppliers' => Supplier::with('users')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $supplier = Supplier::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['user_email'])) {
            User::create([
                'supplier_id' => $supplier->id,
                'name' => $data['user_name'] ?? $supplier->name,
                'email' => $data['user_email'],
                'password' => Hash::make($data['user_password']),
                'role' => UserRole::Supplier,
                'is_active' => true,
            ]);
        }

        return redirect()
            ->route('settings.suppliers.index')
            ->with('success', 'Supplier created.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('settings.suppliers.index')
            ->with('success', 'Supplier updated.');
    }
}
