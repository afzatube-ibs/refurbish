<?php

namespace App\Console\Commands;

use App\Enums\ReturnStatus;
use App\Models\DispatchReport;
use App\Models\ReturnModel;
use App\Services\SupplierLedgerService;
use Illuminate\Console\Command;

class BackfillSupplierLedger extends Command
{
    protected $signature = 'ledger:backfill';

    protected $description = 'Post missing supplier ledger entries from existing dispatch and return records';

    public function handle(SupplierLedgerService $ledgerService): int
    {
        $dispatchCount = 0;
        $returnCount = 0;

        DispatchReport::query()->with('items')->orderBy('id')->each(function (DispatchReport $report) use ($ledgerService, &$dispatchCount) {
            $ledgerService->postDispatch($report);
            $dispatchCount++;
        });

        ReturnModel::query()
            ->where('return_status', ReturnStatus::Confirmed)
            ->with('returnItems')
            ->orderBy('id')
            ->each(function (ReturnModel $return) use ($ledgerService, &$returnCount) {
                $ledgerService->postReturnReversal($return);
                $returnCount++;
            });

        $this->info("Backfilled {$dispatchCount} dispatch and {$returnCount} return ledger entries.");

        return self::SUCCESS;
    }
}
