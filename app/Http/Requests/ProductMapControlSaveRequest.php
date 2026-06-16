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
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.scope' => ['required', 'string', Rule::in(['parent', 'variant', 'simple'])],
            'changes.*.index' => ['nullable', 'integer', 'min:0'],
            'changes.*.field' => ['required', 'string', Rule::in(['ibs_model', 'sm_model', 'product_category', 'rate', 'ibs_stock', 'low_warning'])],
            'changes.*.value' => ['nullable'],
            'changes.*.mode' => ['nullable', 'string', Rule::in(['set', 'increase', 'decrease'])],
            'changes.*.amount' => ['nullable', 'numeric', 'min:0'],
            'changes.*.reason' => ['nullable', 'string', Rule::in(ProductMapLocalControlService::STOCK_REASONS)],
            'changes.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
