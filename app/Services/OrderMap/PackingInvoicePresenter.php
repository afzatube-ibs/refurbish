<?php

namespace App\Services\OrderMap;

use App\Models\Connection;
use App\Models\Order;
use Carbon\CarbonInterface;

class PackingInvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing(['items', 'supplier']);
        $snapshot = is_array($order->source_snapshot) ? $order->source_snapshot : [];
        $connection = Connection::getInstance();

        return [
            'business_name' => 'Lokkisona',
            'business_line' => $this->businessLine($connection),
            'order' => $order,
            'order_number' => (string) $order->source_order_id,
            'order_date' => $order->oc_created_at,
            'ibs_status' => $order->sfm_status?->label() ?? '—',
            'print_at' => now(),
            'customer_name' => (string) $order->customer_name,
            'customer_phone' => (string) $order->customer_phone,
            'customer_address' => (string) ($order->customer_address ?? ''),
            'consignment_id' => filled($order->consignment_id) ? (string) $order->consignment_id : null,
            'courier_name' => filled($order->courier_name) ? (string) $order->courier_name : null,
            'cod_amount' => $this->codAmount($order, $snapshot),
            'total_qty' => (int) $order->items->sum('quantity'),
            'line_items' => $this->lineItems($order),
            'is_manual' => str_starts_with((string) $order->source_order_id, 'MAN-'),
        ];
    }

    protected function businessLine(Connection $connection): string
    {
        $supplier = trim((string) ($connection->supplier_filter ?? ''));

        if ($supplier !== '') {
            return 'Supplier fulfillment · '.strtoupper($supplier);
        }

        $host = parse_url((string) $connection->store_url, PHP_URL_HOST);

        return $host ? 'Supplier fulfillment · '.$host : 'Supplier fulfillment';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function codAmount(Order $order, array $snapshot): ?float
    {
        if (isset($snapshot['cod_amount']) && is_numeric($snapshot['cod_amount'])) {
            return (float) $snapshot['cod_amount'];
        }

        if ($order->sale_amount !== null && (float) $order->sale_amount > 0) {
            return (float) $order->sale_amount;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function lineItems(Order $order): array
    {
        return $order->items->map(function ($item) {
            $option = trim((string) ($item->option_value ?? ''));
            if ($option === '' && filled($item->variant_label)) {
                $option = (string) $item->variant_label;
            }

            $model = trim((string) ($item->model ?? ''));
            $qty = (int) $item->quantity;
            $unitPrice = (float) $item->sale_price;
            $lineTotal = $unitPrice * $qty;

            return [
                'product_name' => (string) $item->product_name,
                'model' => $model !== '' ? $model : '—',
                'option' => $option !== '' ? $option : '—',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        })->all();
    }

    public function formatDate(?CarbonInterface $date): string
    {
        return $date?->format('j M Y') ?? '—';
    }

    public function formatDateTime(?CarbonInterface $date): string
    {
        return $date?->format('j M Y, h:i A') ?? '—';
    }

    public function formatMoney(?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return number_format($amount, 2);
    }
}
