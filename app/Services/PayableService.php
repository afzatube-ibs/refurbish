<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\DispatchReportItem;
use App\Models\ReturnItem;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayableService
{
    public function __construct(
        protected ActivityLogService $activityLog
    ) {}

    /**
     * @param  array{from?: string, to?: string}|null  $dateRange
     * @return array{
     *     delivered_cost: float,
     *     returned_cost: float,
     *     received_from_supplier: float,
     *     net_payable: float
     * }
     */
    public function summary(?int $supplierId, ?array $dateRange = null): array
    {
        $deliveredQuery = DispatchReportItem::query()
            ->whereHas('dispatchReport', function ($query) use ($supplierId, $dateRange) {
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }

                if ($dateRange['from'] ?? null) {
                    $query->whereDate('dispatch_date', '>=', $dateRange['from']);
                }

                if ($dateRange['to'] ?? null) {
                    $query->whereDate('dispatch_date', '<=', $dateRange['to']);
                }
            });

        $deliveredCost = (float) (clone $deliveredQuery)
            ->selectRaw('COALESCE(SUM(quantity * supplier_cost_snapshot), 0) as total')
            ->value('total');

        $returnedQuery = ReturnItem::query()
            ->whereHas('returnRecord', function ($query) use ($supplierId, $dateRange) {
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }

                $query->where('return_status', ReturnStatus::Confirmed);

                if ($dateRange['from'] ?? null) {
                    $query->whereDate('received_date', '>=', $dateRange['from']);
                }

                if ($dateRange['to'] ?? null) {
                    $query->whereDate('received_date', '<=', $dateRange['to']);
                }
            });

        $returnedCost = (float) (clone $returnedQuery)
            ->selectRaw('COALESCE(SUM(quantity * supplier_cost_snapshot), 0) as total')
            ->value('total');

        $paymentsQuery = SupplierPayment::query();

        if ($supplierId) {
            $paymentsQuery->where('supplier_id', $supplierId);
        }

        if ($dateRange['from'] ?? null) {
            $paymentsQuery->whereDate('payment_date', '>=', $dateRange['from']);
        }

        if ($dateRange['to'] ?? null) {
            $paymentsQuery->whereDate('payment_date', '<=', $dateRange['to']);
        }

        $receivedFromSupplier = (float) $paymentsQuery->sum('amount');
        $netPayable = $deliveredCost - $returnedCost - $receivedFromSupplier;

        return [
            'delivered_cost' => round($deliveredCost, 2),
            'returned_cost' => round($returnedCost, 2),
            'received_from_supplier' => round($receivedFromSupplier, 2),
            'net_payable' => round($netPayable, 2),
        ];
    }

    public function recordPayment(
        int $supplierId,
        float $amount,
        \DateTimeInterface $paymentDate,
        User $recordedBy,
        ?string $reference = null,
        ?string $notes = null
    ): SupplierPayment {
        return DB::transaction(function () use ($supplierId, $amount, $paymentDate, $recordedBy, $reference, $notes) {
            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplierId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by' => $recordedBy->id,
            ]);

            $this->activityLog->log('payable.payment_recorded', SupplierPayment::class, $payment->id, [
                'supplier_id' => $supplierId,
                'amount' => $amount,
            ]);

            return $payment;
        });
    }
}
