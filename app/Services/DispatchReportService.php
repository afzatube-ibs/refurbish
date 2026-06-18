<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\DispatchBatchItem;
use App\Models\DispatchBatchOrder;
use App\Models\DispatchReport;
use App\Models\DispatchReportItem;
use Illuminate\Support\Collection;

class DispatchReportService
{
    /**
     * @param  array{
     *     supplier_id?: int|null,
     *     connection_id?: int|null,
     *     from?: string|null,
     *     to?: string|null,
     *     courier?: string|null,
     *     search?: string|null,
     *     user_supplier_id?: int|null
     * }  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(array $filters = []): Collection
    {
        $batchLines = $this->batchLines($filters);
        $legacyLines = $this->legacyLines($filters);

        return $batchLines
            ->concat($legacyLines)
            ->sortByDesc(fn (array $line) => $line['date'].'|'.$line['order_no'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{orders: int, qty: int, dispatch_cost: float}
     */
    public function totals(Collection $lines): array
    {
        return [
            'orders' => $lines->pluck('order_id')->unique()->count(),
            'qty' => (int) $lines->sum('qty'),
            'dispatch_cost' => round((float) $lines->sum('supplier_total_cost'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function batchLines(array $filters): Collection
    {
        $query = DispatchBatchItem::query()
            ->with([
                'batch.supplier',
                'batch.connection',
                'order',
            ]);

        $query->whereHas('batch', function ($batchQuery) use ($filters) {
            if ($filters['user_supplier_id'] ?? null) {
                $batchQuery->where('supplier_id', $filters['user_supplier_id']);
            } elseif ($filters['supplier_id'] ?? null) {
                $batchQuery->where('supplier_id', $filters['supplier_id']);
            }

            if ($filters['connection_id'] ?? null) {
                $batchQuery->where('connection_id', $filters['connection_id']);
            }

            if ($filters['from'] ?? null) {
                $batchQuery->whereDate('dispatch_date', '>=', $filters['from']);
            }

            if ($filters['to'] ?? null) {
                $batchQuery->whereDate('dispatch_date', '<=', $filters['to']);
            }
        });

        if ($courier = trim((string) ($filters['courier'] ?? ''))) {
            $query->whereHas('batch.batchOrders', function ($orderQuery) use ($courier) {
                $orderQuery->where('courier', 'like', '%'.$courier.'%');
            });
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($lineQuery) use ($search) {
                $lineQuery->whereHas('batch.batchOrders', function ($orderQuery) use ($search) {
                    $orderQuery->where('order_no', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                })->orWhereHas('order', function ($orderQuery) use ($search) {
                    $orderQuery->where('source_order_id', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%');
                });
            });
        }

        $batchOrders = DispatchBatchOrder::query()
            ->get()
            ->groupBy(fn (DispatchBatchOrder $row) => $row->dispatch_batch_id.'|'.$row->order_id);

        return $query->get()->map(function (DispatchBatchItem $item) use ($batchOrders) {
            $batch = $item->batch;
            $batchOrderKey = $batch->id.'|'.$item->order_id;
            $batchOrder = $batchOrders->get($batchOrderKey)?->first();
            $order = $item->order;

            return [
                'date' => $batch->dispatch_date->format('Y-m-d'),
                'order_id' => $item->order_id,
                'order_no' => $batchOrder?->order_no ?? $order?->source_order_id ?? '—',
                'customer' => $batchOrder?->customer_name ?? $order?->customer_name ?? '—',
                'phone' => $batchOrder?->phone ?? $order?->customer_phone ?? '—',
                'supplier' => $batch->supplier?->name ?? '—',
                'store' => $this->storeLabel($batch->connection),
                'product' => $item->product_name,
                'qty' => (int) $item->qty,
                'supplier_unit_cost' => (float) $item->supplier_unit_cost,
                'supplier_total_cost' => (float) $item->supplier_total_cost,
                'courier' => $batchOrder?->courier ?? $order?->courier_name ?? '—',
                'consignment_id' => $batchOrder?->consignment_id ?? $order?->consignment_id ?? '—',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function legacyLines(array $filters): Collection
    {
        if ($filters['connection_id'] ?? null) {
            return collect();
        }

        $query = DispatchReportItem::query()
            ->with([
                'dispatchReport.supplier',
                'dispatchReport.order.connection',
                'orderItem',
            ])
            ->whereHas('dispatchReport', function ($reportQuery) use ($filters) {
                $reportQuery->whereNull('dispatch_batch_id');

                if ($filters['user_supplier_id'] ?? null) {
                    $reportQuery->where('supplier_id', $filters['user_supplier_id']);
                } elseif ($filters['supplier_id'] ?? null) {
                    $reportQuery->where('supplier_id', $filters['supplier_id']);
                }

                if ($filters['from'] ?? null) {
                    $reportQuery->whereDate('dispatch_date', '>=', $filters['from']);
                }

                if ($filters['to'] ?? null) {
                    $reportQuery->whereDate('dispatch_date', '<=', $filters['to']);
                }

                if ($courier = trim((string) ($filters['courier'] ?? ''))) {
                    $reportQuery->where('courier', 'like', '%'.$courier.'%');
                }
            });

        if ($filters['connection_id'] ?? null) {
            $query->whereHas('dispatchReport.order', function ($orderQuery) use ($filters) {
                $orderQuery->where('connection_id', $filters['connection_id']);
            });
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($lineQuery) use ($search) {
                $lineQuery->whereHas('dispatchReport.order', function ($orderQuery) use ($search) {
                    $orderQuery->where('source_order_id', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%');
                })->orWhereHas('dispatchReport', function ($reportQuery) use ($search) {
                    $reportQuery->where('consignment_id', 'like', '%'.$search.'%');
                });
            });
        }

        return $query->get()->map(function (DispatchReportItem $item) {
            $report = $item->dispatchReport;
            $order = $report->order;
            $orderItem = $item->orderItem;
            $qty = (int) $item->quantity;
            $unitCost = (float) $item->supplier_cost_snapshot;
            $totalCost = round($qty * $unitCost, 2);

            return [
                'date' => $report->dispatch_date->format('Y-m-d'),
                'order_id' => $report->order_id,
                'order_no' => $order?->source_order_id ?? '—',
                'customer' => $order?->customer_name ?? '—',
                'phone' => $order?->customer_phone ?? '—',
                'supplier' => $report->supplier?->name ?? '—',
                'store' => $this->defaultStoreLabel(),
                'product' => $orderItem?->product_name ?? '—',
                'qty' => $qty,
                'supplier_unit_cost' => $unitCost,
                'supplier_total_cost' => $totalCost,
                'courier' => $report->courier ?? $order?->courier_name ?? '—',
                'consignment_id' => $report->consignment_id ?? $order?->consignment_id ?? '—',
            ];
        });
    }

    protected function defaultStoreLabel(): string
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return $this->storeLabel($connection);
    }

    protected function storeLabel(?Connection $connection): string
    {
        if (! $connection) {
            return '—';
        }

        if (! filled($connection->store_url)) {
            return 'Store #'.$connection->id;
        }

        $host = parse_url($connection->store_url, PHP_URL_HOST);

        return $host ?: $connection->store_url;
    }
}
