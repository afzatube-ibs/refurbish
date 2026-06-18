<?php

namespace App\Http\Requests;

use App\Enums\CollectionSource;
use App\Services\OperationalDefaultsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $defaults = app(OperationalDefaultsService::class);

        $this->merge([
            'supplier_id' => $this->input('supplier_id', $defaults->defaultSupplierId()),
            'connection_id' => $this->input('connection_id', $defaults->defaultConnectionId()),
        ]);
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
