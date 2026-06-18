<?php

namespace App\Http\Requests;

use App\Services\OperationalDefaultsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $defaults = app(OperationalDefaultsService::class)->manualOrderDefaults();

        $this->merge([
            'source_store' => $this->input('source_store', $defaults['source_store']),
            'source_type' => $this->input('source_type', $defaults['source_type']),
        ]);
    }

    public function rules(): array
    {
        return [
            'source_store' => ['nullable', 'string', Rule::in(['lokkisona'])],
            'source_type' => ['nullable', 'string', Rule::in(['inbox', 'phone', 'offline', 'other'])],
            'reference_note' => ['nullable', 'string', 'max:500'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_address' => ['required', 'string', 'max:2000'],
            'city_zone' => ['nullable', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_product_id' => ['nullable', 'string', 'max:64'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.model' => ['nullable', 'string', 'max:255'],
            'items.*.option' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Customer name is required.',
            'customer_phone.required' => 'Phone number is required.',
            'customer_address.required' => 'Delivery address is required.',
            'items.required' => 'Add at least one product line.',
            'items.min' => 'Add at least one product line.',
            'items.*.product_name.required' => 'Each line needs a product name.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.sale_price.required' => 'Sale price is required for each line.',
            'items.*.sale_price.min' => 'Sale price cannot be negative.',
        ];
    }
}
