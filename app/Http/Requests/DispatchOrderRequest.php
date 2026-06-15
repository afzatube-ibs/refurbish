<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSupplier() ?? false;
    }

    public function rules(): array
    {
        return [
            'courier' => ['required', 'string', 'max:255'],
            'consignment_id' => ['required', 'string', 'max:255'],
            'dispatch_date' => ['nullable', 'date'],
        ];
    }
}
