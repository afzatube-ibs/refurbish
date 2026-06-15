<?php

namespace App\Http\Requests\Settings;

use App\Enums\SfmOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $statusValues = array_column(SfmOrderStatus::cases(), 'value');

        return [
            'mappings' => ['required', 'array'],
            'mappings.*.id' => ['required', 'integer', 'exists:order_status_mappings,id'],
            'mappings.*.sfm_status' => ['required', 'string', Rule::in($statusValues)],
        ];
    }
}
