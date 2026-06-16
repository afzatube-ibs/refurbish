<?php

namespace App\Services;

use App\Enums\SfmOrderStatus;
use App\Models\DispatchReport;
use App\Models\DispatchReportItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DispatchService
{
    public function create(
        Order $order,
        string $courier,
        string $consignmentId,
        ?\DateTimeInterface $dispatchDate = null,
        ?int $userId = null
    ): DispatchReport {
        $courier = trim($courier);
        $consignmentId = trim($consignmentId);

        if ($courier === '') {
            throw new InvalidArgumentException('Courier is required for dispatch.');
        }

        if ($consignmentId === '') {
            throw new InvalidArgumentException('Consignment ID is required for dispatch.');
        }

        return DB::transaction(function () use ($order, $courier, $consignmentId, $dispatchDate, $userId) {
            $dispatchDate = $dispatchDate ?? now();
            $userId = $userId ?? auth()->id();

            $report = DispatchReport::query()->create([
                'order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'dispatch_date' => $dispatchDate,
                'courier' => $courier,
                'consignment_id' => $consignmentId,
                'created_by' => $userId,
            ]);

            $order->loadMissing(['items.supplierProduct']);

            foreach ($order->items as $item) {
                $cost = $item->supplier_product_cost_snapshot
                    ?? $item->supplierProduct?->supplier_cost
                    ?? 0;

                DispatchReportItem::query()->create([
                    'dispatch_report_id' => $report->id,
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                    'supplier_cost_snapshot' => $cost,
                ]);

                $item->update([
                    'supplier_product_cost_snapshot' => $cost,
                    'cost_snapshotted_at' => now(),
                ]);
            }

            $order->update([
                'sfm_status' => SfmOrderStatus::Dispatched,
                'courier_name' => $courier,
                'consignment_id' => $consignmentId,
            ]);

            return $report->load('items');
        });
    }
}
