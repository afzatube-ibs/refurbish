<?php

namespace App\Services\OrderMap;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PackingInvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing(['items']);
        $snapshot = is_array($order->source_snapshot) ? $order->source_snapshot : [];
        $snapshotItems = $this->snapshotItemsById($snapshot);

        $paymentMethod = trim((string) ($snapshot['payment_method'] ?? ''));
        $steadfast = $this->steadfastInfo($order, $snapshot);
        $hasSteadfast = $this->hasSteadfast($order, $snapshot, $steadfast);
        $products = $this->products($order, $snapshotItems);
        $paymentStatus = $this->paymentStatus($snapshot, $paymentMethod);

        return [
            'store_name' => 'Lokkisona Baby Store',
            'store_logo' => asset('images/lokkisona-invoice-logo.png'),
            'store_phone' => '01932263545',
            'store_url' => 'www.lokkisona.com',
            'order_id' => (string) $order->source_order_id,
            'invoice_no' => $this->invoiceNo($snapshot),
            'order_date' => $this->formatOrderDate($order, $snapshot),
            'customer_name' => $this->displayValue($order->customer_name),
            'customer_phone' => $this->displayValue($order->customer_phone),
            'shipping_address' => $this->shippingAddress($order, $snapshot),
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : 'Not available',
            'payment_logo' => $this->paymentLogoType($paymentMethod),
            'shipping_method' => $this->shippingMethod($order, $snapshot, $steadfast),
            'payment_status' => $paymentStatus,
            'has_steadfast' => $hasSteadfast,
            'products' => $products,
            'totals' => $this->totals($order, $snapshot, $products),
            'steadfast' => $steadfast,
            'qr_payload' => $this->qrPayload($snapshot, $steadfast),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function invoiceNo(array $snapshot): string
    {
        $invoiceNo = trim((string) ($snapshot['invoice_no'] ?? ''));
        if ($invoiceNo !== '') {
            $prefix = trim((string) ($snapshot['invoice_prefix'] ?? ''));

            return $prefix !== '' ? $prefix.$invoiceNo : $invoiceNo;
        }

        return 'Pending';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function formatOrderDate(Order $order, array $snapshot): string
    {
        if ($order->oc_created_at instanceof CarbonInterface) {
            return $order->oc_created_at->format('d/m/Y');
        }

        $createdAt = trim((string) ($snapshot['created_at'] ?? $snapshot['date_added'] ?? ''));
        if ($createdAt !== '') {
            try {
                return Carbon::parse($createdAt)->format('d/m/Y');
            } catch (\Throwable) {
                return $createdAt;
            }
        }

        return 'Not available';
    }

    protected function displayValue(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : 'Not available';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function shippingAddress(Order $order, array $snapshot): string
    {
        $address = trim((string) ($order->customer_address ?? ''));
        if ($address !== '') {
            return $address;
        }

        $parts = array_filter([
            trim((string) ($snapshot['shipping_address_1'] ?? '')),
            trim((string) ($snapshot['shipping_address_2'] ?? '')),
            trim((string) ($snapshot['shipping_city'] ?? '')),
            trim((string) ($snapshot['customer_address'] ?? '')),
        ]);

        if ($parts !== []) {
            return implode(', ', array_unique($parts));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, string>  $steadfast
     */
    protected function shippingMethod(Order $order, array $snapshot, array $steadfast): string
    {
        if ($this->hasSteadfastData($steadfast)) {
            return 'Steadfast Courier';
        }

        $method = trim((string) ($snapshot['shipping_method'] ?? ''));
        if ($method !== '') {
            return $method;
        }

        $courier = trim((string) ($order->courier_name ?? ''));

        return $courier !== '' ? $courier : 'Not available';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function paymentStatus(array $snapshot, string $paymentMethod): string
    {
        $status = trim((string) ($snapshot['payment_status'] ?? ''));
        if ($status !== '') {
            return ucfirst(strtolower($status));
        }

        $method = strtolower($paymentMethod);
        if (str_contains($method, 'bkash') || str_contains($method, 'b-kash') || str_contains($method, 'paid')) {
            return 'Paid';
        }

        return 'Due';
    }

    protected function paymentLogoType(string $paymentMethod): string
    {
        $method = strtolower($paymentMethod);
        if (str_contains($method, 'bkash') || str_contains($method, 'b-kash')) {
            return 'bkash';
        }
        if (str_contains($method, 'cash') || str_contains($method, 'cod') || str_contains($method, 'delivery')) {
            return 'cod';
        }

        return 'other';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, string>
     */
    protected function steadfastInfo(Order $order, array $snapshot): array
    {
        $consignmentId = trim((string) ($order->consignment_id ?? ''));
        if ($consignmentId === '') {
            $consignmentId = trim((string) ($snapshot['consignment_id'] ?? ''));
        }

        $parcelId = trim((string) ($snapshot['parcel_id'] ?? ''));
        if ($parcelId === '' && $consignmentId !== '') {
            $parcelId = $consignmentId;
        }

        return [
            'consignment_id' => $consignmentId,
            'parcel_id' => $parcelId,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, string>  $steadfast
     */
    protected function hasSteadfast(Order $order, array $snapshot, array $steadfast): bool
    {
        if ($this->hasSteadfastData($steadfast)) {
            return true;
        }

        $shipping = strtolower($this->shippingMethod($order, $snapshot, ['consignment_id' => '', 'parcel_id' => '']));
        $courier = strtolower((string) ($order->courier_name ?? ''));

        return str_contains($shipping, 'steadfast') || str_contains($courier, 'steadfast');
    }

    /**
     * @param  array<string, string>  $steadfast
     */
    protected function hasSteadfastData(array $steadfast): bool
    {
        return ($steadfast['consignment_id'] ?? '') !== ''
            || ($steadfast['parcel_id'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, string>  $steadfast
     */
    protected function qrPayload(array $snapshot, array $steadfast): string
    {
        foreach (['qr_code', 'tracking_url', 'tracking_code'] as $key) {
            $value = trim((string) ($snapshot[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (($steadfast['consignment_id'] ?? '') !== '') {
            return 'https://steadfast.com.bd/t/'.rawurlencode($steadfast['consignment_id']);
        }

        if (($steadfast['parcel_id'] ?? '') !== '') {
            return 'https://steadfast.com.bd/t/'.rawurlencode($steadfast['parcel_id']);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, array<string, mixed>>
     */
    protected function snapshotItemsById(array $snapshot): array
    {
        $indexed = [];
        $items = $snapshot['items'] ?? $snapshot['products'] ?? [];

        if (! is_array($items)) {
            return $indexed;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['source_product_id'] ?? $item['product_id'] ?? ''));
            if ($id !== '') {
                $indexed[$id] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, array<string, mixed>>  $snapshotItems
     * @return list<array<string, mixed>>
     */
    protected function products(Order $order, array $snapshotItems): array
    {
        $placeholder = asset('images/lokkisona-invoice-placeholder.svg');

        return $order->items->map(function (OrderItem $item) use ($snapshotItems, $placeholder) {
            $snapshotItem = $snapshotItems[(string) $item->source_product_id] ?? [];
            $image = trim((string) ($snapshotItem['image'] ?? $snapshotItem['product_image'] ?? ''));
            $qty = (int) $item->quantity;
            $unitPrice = (float) $item->sale_price;

            return [
                'image' => $image !== '' ? $image : $placeholder,
                'name' => (string) $item->product_name,
                'model' => trim((string) ($item->model ?? '')) !== '' ? (string) $item->model : 'Not available',
                'options' => $this->productOptions($item, $snapshotItem),
                'quantity' => $qty,
                'price' => $this->formatMoney($unitPrice),
                'total' => $this->formatMoney($unitPrice * $qty),
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $snapshotItem
     * @return list<array{name: string, value: string}>
     */
    protected function productOptions(OrderItem $item, array $snapshotItem): array
    {
        $options = [];

        if (isset($snapshotItem['options']) && is_array($snapshotItem['options'])) {
            foreach ($snapshotItem['options'] as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $name = trim((string) ($option['name'] ?? $option['option_name'] ?? ''));
                $value = trim((string) ($option['value'] ?? $option['option_value'] ?? ''));
                if ($name !== '' || $value !== '') {
                    $options[] = [
                        'name' => $name !== '' ? $name : 'Option',
                        'value' => $value !== '' ? $value : '—',
                    ];
                }
            }
        }

        if ($options === []) {
            $name = trim((string) ($item->option_name ?? ''));
            $value = trim((string) ($item->option_value ?? ''));
            if ($name === '' && $value === '' && filled($item->variant_label)) {
                $label = (string) $item->variant_label;
                if (str_contains($label, ':')) {
                    [$name, $value] = array_map('trim', explode(':', $label, 2));
                } else {
                    $value = $label;
                }
            }

            if ($name !== '' || $value !== '') {
                $options[] = [
                    'name' => $name !== '' ? $name : 'Option',
                    'value' => $value !== '' ? $value : '—',
                ];
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $products
     * @return list<array{title: string, text: string}>
     */
    protected function totals(Order $order, array $snapshot, array $products): array
    {
        foreach (['totals', 'order_totals'] as $key) {
            if (! isset($snapshot[$key]) || ! is_array($snapshot[$key])) {
                continue;
            }

            $rows = [];
            foreach ($snapshot[$key] as $total) {
                if (! is_array($total)) {
                    continue;
                }

                $title = trim((string) ($total['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $value = $total['text'] ?? $total['value'] ?? null;
                $rows[] = [
                    'title' => $title,
                    'text' => is_numeric($value) ? $this->formatMoney((float) $value) : (string) $value,
                ];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        $subtotal = 0.0;
        foreach ($products as $product) {
            $subtotal += (float) str_replace(',', '', (string) ($product['total'] ?? '0'));
        }

        $grand = null;
        if (isset($snapshot['order_total']) && is_numeric($snapshot['order_total'])) {
            $grand = (float) $snapshot['order_total'];
        } elseif ($order->sale_amount !== null && (float) $order->sale_amount > 0) {
            $grand = (float) $order->sale_amount;
        } else {
            $grand = $subtotal;
        }

        return [
            ['title' => 'Sub-Total', 'text' => $this->formatMoney($subtotal)],
            ['title' => 'Total', 'text' => $this->formatMoney($grand)],
        ];
    }

    public function formatMoney(?float $amount): string
    {
        if ($amount === null) {
            return '0.00';
        }

        return number_format($amount, 2);
    }
}
