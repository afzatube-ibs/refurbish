<?php

namespace App\Services;

use App\Enums\DispatchBatchItemCostStatus;
use App\Enums\DispatchBatchStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\DispatchBatch;
use App\Models\DispatchBatchItem;
use App\Models\DispatchBatchOrder;
use App\Models\DispatchReport;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DispatchBatchService
{
    public function __construct(
        protected DispatchService $dispatchService,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * @param  array{
     *     order_ids: list<int>,
     *     dispatch_date?: string,
     *     orders: array<int, array{courier?: string, consignment_id: string}>
     * }  $payload
     */
    public function createFromPackedOrders(array $payload, User $user): DispatchBatch
    {
        $orderIds = array_values(array_unique(array_map('intval', $payload['order_ids'] ?? [])));
        $orderMeta = [];
        foreach ($payload['orders'] ?? [] as $key => $meta) {
            if (is_array($meta)) {
                $orderMeta[(int) $key] = $meta;
            }
        }

        if ($orderIds === []) {
            throw new InvalidArgumentException('Select at least one order for dispatch batch.');
        }

        $orders = Order::query()
            ->with(['items.supplierProduct'])
            ->whereIn('id', $orderIds)
            ->get()
            ->keyBy('id');

        if ($orders->count() !== count($orderIds)) {
            throw new InvalidArgumentException('One or more selected orders could not be found.');
        }

        if ($user->isSupplier()) {
            $foreign = $orders->first(fn (Order $order) => $order->supplier_id !== $user->supplier_id);
            if ($foreign) {
                throw new InvalidArgumentException('You can only dispatch your own orders.');
            }
        }

        $supplierIds = $orders->pluck('supplier_id')->unique();
        if ($supplierIds->count() > 1) {
            throw new InvalidArgumentException('All orders in a batch must belong to the same supplier.');
        }

        $supplierId = (int) $supplierIds->first();

        foreach ($orders as $order) {
            if ($order->sfm_status !== SfmOrderStatus::Packed) {
                throw new InvalidArgumentException(
                    'Order #'.$order->source_order_id.' is not packed and cannot be added to a dispatch batch.'
                );
            }
        }

        $alreadyBatched = DispatchBatchOrder::query()
            ->whereIn('order_id', $orderIds)
            ->pluck('order_no')
            ->all();

        if ($alreadyBatched !== []) {
            throw new InvalidArgumentException(
                'Orders already included in a dispatch batch: '.implode(', ', $alreadyBatched)
            );
        }

        foreach ($orderIds as $orderId) {
            $consignment = trim((string) ($orderMeta[$orderId]['consignment_id'] ?? ''));
            if ($consignment === '') {
                $order = $orders->get($orderId);
                throw new InvalidArgumentException(
                    'Consignment ID is required for order #'.($order?->source_order_id ?? $orderId).'.'
                );
            }
        }

        $dispatchDate = isset($payload['dispatch_date'])
            ? new \DateTimeImmutable($payload['dispatch_date'])
            : new \DateTimeImmutable('today');

        $connectionId = $this->activeConnectionId();
        $ibsModels = $this->ibsModelLookup($supplierId, $orders);

        return DB::transaction(function () use (
            $orders,
            $orderIds,
            $orderMeta,
            $user,
            $supplierId,
            $connectionId,
            $dispatchDate,
            $ibsModels,
        ) {
            $snapshots = $this->buildSnapshots($orders, $ibsModels, $orderMeta);
            $batchNo = $this->nextBatchNo($supplierId, $dispatchDate);

            $batch = DispatchBatch::query()->create([
                'batch_no' => $batchNo,
                'supplier_id' => $supplierId,
                'connection_id' => $connectionId,
                'dispatch_date' => $dispatchDate,
                'status' => DispatchBatchStatus::Finalized,
                'total_orders' => $snapshots['total_orders'],
                'total_items' => $snapshots['total_items'],
                'total_qty' => $snapshots['total_qty'],
                'total_supplier_cost' => $snapshots['total_supplier_cost'],
                'created_by' => $user->id,
                'finalized_at' => now(),
            ]);

            foreach ($snapshots['batch_orders'] as $batchOrderRow) {
                DispatchBatchOrder::query()->create(array_merge($batchOrderRow, [
                    'dispatch_batch_id' => $batch->id,
                ]));
            }

            foreach ($snapshots['batch_items'] as $batchItemRow) {
                DispatchBatchItem::query()->create(array_merge($batchItemRow, [
                    'dispatch_batch_id' => $batch->id,
                ]));
            }

            foreach ($orderIds as $orderId) {
                /** @var Order $order */
                $order = $orders->get($orderId);
                $meta = $orderMeta[$orderId] ?? [];
                $courier = trim((string) ($meta['courier'] ?? ''));
                $consignmentId = trim((string) ($meta['consignment_id'] ?? ''));

                $report = $this->dispatchService->create(
                    $order,
                    $courier,
                    $consignmentId,
                    $dispatchDate,
                    $user->id,
                );

                DispatchReport::query()
                    ->where('id', $report->id)
                    ->update(['dispatch_batch_id' => $batch->id]);
            }

            $this->activityLog->log('dispatch.batch.created', DispatchBatch::class, $batch->id, [
                'batch_no' => $batch->batch_no,
                'supplier_id' => $supplierId,
                'total_orders' => $batch->total_orders,
                'total_qty' => $batch->total_qty,
            ]);

            return $batch->fresh(['supplier', 'connection', 'batchOrders', 'batchItems', 'creator']);
        });
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, string|null>  $ibsModels
     * @param  array<int, array{courier?: string, consignment_id?: string}>  $orderMeta
     * @return array{
     *     total_orders: int,
     *     total_items: int,
     *     total_qty: int,
     *     total_supplier_cost: float,
     *     batch_orders: list<array<string, mixed>>,
     *     batch_items: list<array<string, mixed>>
     * }
     */
    protected function buildSnapshots(Collection $orders, array $ibsModels, array $orderMeta): array
    {
        $batchOrders = [];
        $batchItems = [];
        $totalQty = 0;
        $totalItems = 0;
        $totalSupplierCost = 0.0;

        foreach ($orders as $order) {
            $orderQty = 0;
            $orderCost = 0.0;
            $meta = $orderMeta[$order->id] ?? [];

            foreach ($order->items as $item) {
                $unitCost = $item->supplier_product_cost_snapshot
                    ?? $item->supplierProduct?->supplier_cost
                    ?? null;
                $unitCostValue = $unitCost !== null ? round((float) $unitCost, 2) : 0.0;
                $costStatus = ($unitCost === null || (float) $unitCost <= 0)
                    ? DispatchBatchItemCostStatus::MissingCost
                    : DispatchBatchItemCostStatus::Ok;
                $lineQty = (int) $item->quantity;
                $lineTotal = round($unitCostValue * $lineQty, 2);

                $ibsKey = $order->supplier_id.'|'.$item->source_product_id;

                $batchItems[] = [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'model' => $item->model,
                    'ibs_model' => $ibsModels[$ibsKey] ?? null,
                    'qty' => $lineQty,
                    'supplier_unit_cost' => $unitCostValue,
                    'supplier_total_cost' => $lineTotal,
                    'cost_status' => $costStatus,
                ];

                $orderQty += $lineQty;
                $orderCost += $lineTotal;
                $totalItems++;
            }

            $totalQty += $orderQty;
            $totalSupplierCost += $orderCost;

            $batchOrders[] = [
                'order_id' => $order->id,
                'order_no' => (string) $order->source_order_id,
                'customer_name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'courier' => trim((string) ($meta['courier'] ?? '')) ?: null,
                'consignment_id' => trim((string) ($meta['consignment_id'] ?? '')),
                'total_qty' => $orderQty,
                'total_supplier_cost' => round($orderCost, 2),
            ];
        }

        return [
            'total_orders' => $orders->count(),
            'total_items' => $totalItems,
            'total_qty' => $totalQty,
            'total_supplier_cost' => round($totalSupplierCost, 2),
            'batch_orders' => $batchOrders,
            'batch_items' => $batchItems,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, string|null>
     */
    protected function ibsModelLookup(int $supplierId, Collection $orders): array
    {
        $sourceIds = $orders
            ->flatMap(fn (Order $order) => $order->items->pluck('source_product_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($sourceIds === []) {
            return [];
        }

        return ProductControlState::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('source_product_id', $sourceIds)
            ->get()
            ->mapWithKeys(fn (ProductControlState $state) => [
                $supplierId.'|'.$state->source_product_id => $state->ibs_model,
            ])
            ->all();
    }

    protected function nextBatchNo(int $supplierId, \DateTimeInterface $dispatchDate): string
    {
        $prefix = $dispatchDate->format('dmY').'-';
        $latest = DispatchBatch::query()
            ->where('supplier_id', $supplierId)
            ->where('batch_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('batch_no');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    protected function activeConnectionId(): ?int
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return $connection?->id;
    }
}
