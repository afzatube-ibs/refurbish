<?php

namespace App\Http\Requests;

use App\Enums\SettlementEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettlementEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'entry_type' => ['required', Rule::enum(SettlementEntryType::class)],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'connection_id' => ['nullable', 'integer', 'exists:connections,id'],
        ];
    }
}
