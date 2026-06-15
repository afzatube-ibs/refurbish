<?php

namespace App\Http\Requests;

use App\Services\ProductMap\ProductMapLocalControlService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductMapControlSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_index' => ['required', 'integer', 'min:0'],
            'parent' => ['nullable', 'array'],
            'parent.ibs_model' => ['nullable', 'string', 'max:255'],
            'parent.sm_model' => ['nullable', 'string', 'max:255'],
            'parent.low_warning' => ['nullable', 'integer', 'min:0'],
            'parent.rate' => ['nullable', 'array'],
            'parent.rate.mode' => ['nullable', Rule::in(['set', 'increase', 'decrease'])],
            'parent.rate.amount' => ['nullable', 'numeric'],
            'parent.rate.value' => ['nullable', 'numeric'],
            'parent.rate.note' => ['nullable', 'string', 'max:500'],
            'parent.ibs_stock' => ['nullable', 'array'],
            'parent.ibs_stock.mode' => ['nullable', Rule::in(['set', 'increase', 'decrease'])],
            'parent.ibs_stock.amount' => ['nullable', 'numeric'],
            'parent.ibs_stock.value' => ['nullable', 'numeric'],
            'parent.ibs_stock.reason' => ['nullable', 'string', Rule::in(ProductMapLocalControlService::STOCK_REASONS)],
            'variants' => ['nullable', 'array'],
            'variants.*.index' => ['required', 'integer', 'min:0'],
            'variants.*.ibs_model' => ['nullable', 'string', 'max:255'],
            'variants.*.sm_model' => ['nullable', 'string', 'max:255'],
            'variants.*.low_warning' => ['nullable'],
            'variants.*.rate' => ['nullable', 'array'],
            'variants.*.rate.mode' => ['nullable', Rule::in(['set', 'increase', 'decrease'])],
            'variants.*.rate.amount' => ['nullable', 'numeric'],
            'variants.*.rate.note' => ['nullable', 'string', 'max:500'],
            'variants.*.ibs_stock' => ['nullable', 'array'],
            'variants.*.ibs_stock.mode' => ['nullable', Rule::in(['set', 'increase', 'decrease'])],
            'variants.*.ibs_stock.amount' => ['nullable', 'numeric'],
            'variants.*.ibs_stock.reason' => ['nullable', 'string', Rule::in(ProductMapLocalControlService::STOCK_REASONS)],
        ];
    }
}
