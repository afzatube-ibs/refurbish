<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Settings\Concerns\NormalizesConnectionFields;
use App\Models\Connection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TestConnectionRequest extends FormRequest
{
    use NormalizesConnectionFields;
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'store_url' => ['required', 'url', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:500'],
            'product_api_endpoint' => ['required', 'string', 'max:255'],
            'order_api_endpoint' => ['required', 'string', 'max:255'],
            'order_status_api_endpoint' => ['required', 'string', 'max:255'],
            'supplier_filter' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank($this->input('api_token')) && blank(Connection::getInstance()->api_token)) {
                $validator->errors()->add('api_token', 'API token is required for connection test.');
            }
        });
    }
}
