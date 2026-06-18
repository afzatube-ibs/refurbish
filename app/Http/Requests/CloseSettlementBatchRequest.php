<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseSettlementBatchRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
