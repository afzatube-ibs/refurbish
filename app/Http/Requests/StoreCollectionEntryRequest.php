<?php

namespace App\Http\Requests;

use App\Enums\CollectionSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'connection_id' => ['nullable', 'integer', 'exists:connections,id'],
            'entry_type' => ['required', 'string', Rule::in([
                'received_by_supplier',
                'payment_to_dropshipper',
                'adjustment',
            ])],
            'collection_source' => ['nullable', 'string', Rule::enum(CollectionSource::class)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
