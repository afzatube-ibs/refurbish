<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDispatchBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSupplier() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $orders = $this->input('orders', []);
        if (is_array($orders)) {
            foreach ($orders as $orderId => $meta) {
                if (! is_array($meta)) {
                    continue;
                }
                $orders[$orderId]['courier'] = isset($meta['courier']) && is_string($meta['courier'])
                    ? trim($meta['courier'])
                    : ($meta['courier'] ?? null);
                $orders[$orderId]['consignment_id'] = isset($meta['consignment_id']) && is_string($meta['consignment_id'])
                    ? trim($meta['consignment_id'])
                    : ($meta['consignment_id'] ?? null);
            }
            $this->merge(['orders' => $orders]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'dispatch_date' => ['nullable', 'date'],
            'orders' => ['required', 'array'],
            'orders.*.consignment_id' => ['required', 'string', 'max:255'],
            'orders.*.courier' => ['nullable', 'string', 'max:255'],
        ];
    }
}
