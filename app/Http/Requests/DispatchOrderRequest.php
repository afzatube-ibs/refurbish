<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSupplier() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'courier' => is_string($this->courier) ? trim($this->courier) : $this->courier,
            'consignment_id' => is_string($this->consignment_id) ? trim($this->consignment_id) : $this->consignment_id,
        ]);
    }

    public function rules(): array
    {
        return [
            'courier' => ['nullable', 'string', 'max:255'],
            'consignment_id' => ['required', 'string', 'max:255'],
            'dispatch_date' => ['nullable', 'date'],
        ];
    }
}
