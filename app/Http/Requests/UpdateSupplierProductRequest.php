<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_cost' => ['required', 'numeric', 'min:0'],
            'supplier_model' => ['nullable', 'string', 'max:255'],
            'supplier_stock' => ['nullable', 'integer', 'min:0'],
            'low_warning' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
