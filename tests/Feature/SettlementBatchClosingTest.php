<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\SettlementBatchDirection;
use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\SettlementBatch;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\PayableService;
use App\Services\SettlementBatchService;
use App\Services\SettlementService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettlementBatchClosingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        $this->supplier = Supplier::query()->firstOrFail();
        $this->connection = Connection::getInstance();
        $this->connection->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'settlement-batch-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_settlement_batch_create_records_direction_for_negative_balance(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -200);

        $batch = app(SettlementBatchService::class)->close(
            $this->supplier->id,
            $this->admin,
            $this->connection->id,
            'Cycle close test',
        );

        $this->assertDatabaseHas('settlement_batches', [
            'id' => $batch->id,
            'supplier_id' => $this->supplier->id,
            'closing_balance' => -200.0,
            'direction' => SettlementBatchDirection::SupplierPaymentCompleted->value,
            'status' => 'closed',
        ]);

        $this->assertSame('Lokkisona paid supplier', $batch->direction->label());
    }

    public function test_settlement_batch_create_records_collection_direction_for_positive_balance(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            150,
            new \DateTimeImmutable('2026-06-05'),
            $this->admin,
        );

        $batch = app(SettlementBatchService::class)->close(
            $this->supplier->id,
            $this->admin,
            $this->connection->id,
        );

        $this->assertSame(150.0, (float) $batch->closing_balance);
        $this->assertSame(SettlementBatchDirection::SupplierCollectionCompleted, $batch->direction);
    }

    public function test_close_resets_active_cycle_balance(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -120);

        app(SettlementBatchService::class)->close(
            $this->supplier->id,
            $this->admin,
            $this->connection->id,
        );

        $summary = app(PayableService::class)->summary(
            $this->supplier->id,
            null,
            $this->connection->id,
            activeCycleOnly: true,
        );

        $this->assertSame(0.0, $summary['net_payable']);
    }

    public function test_settlement_history_page_loads(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -80);
        $batch = app(SettlementBatchService::class)->close($this->supplier->id, $this->admin, $this->connection->id);

        $this->actingAs($this->admin)
            ->get(route('settlements.index'))
            ->assertOk()
            ->assertSee('Settlement History', false)
            ->assertSee($batch->batch_no, false)
            ->assertSee('Payment to supplier', false);
    }

    public function test_batch_detail_page_loads(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -95);
        $batch = app(SettlementBatchService::class)->close(
            $this->supplier->id,
            $this->admin,
            $this->connection->id,
            'Detail notes',
        );

        $this->actingAs($this->admin)
            ->get(route('settlements.show', $batch))
            ->assertOk()
            ->assertSee($batch->batch_no, false)
            ->assertSee('Opening Balance', false)
            ->assertSee('Closing Balance', false)
            ->assertSee('Transactions Included', false)
            ->assertSee('Lokkisona paid supplier', false)
            ->assertSee('Detail notes', false)
            ->assertSee(number_format(-95, 2), false);
    }

    public function test_payables_close_button_only_when_balance_not_zero(): void
    {
        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertDontSee('Close Current Settlement', false);

        $this->postLedger(LedgerEntryType::DispatchCost, -50);

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Close Current Settlement', false);
    }

    public function test_ledger_shows_batch_reference_link(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -60);
        $batch = app(SettlementBatchService::class)->close($this->supplier->id, $this->admin, $this->connection->id);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee($batch->batch_no, false);
    }

    public function test_ledger_entries_are_grouped_to_batch(): void
    {
        $this->postLedger(LedgerEntryType::DispatchCost, -40);

        $batch = app(SettlementBatchService::class)->close($this->supplier->id, $this->admin, $this->connection->id);

        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $this->supplier->id,
            'settlement_batch_id' => $batch->id,
        ]);
    }

    private function postLedger(LedgerEntryType $type, float $amount): SupplierLedgerEntry
    {
        return SupplierLedgerEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'connection_id' => $this->connection->id,
            'type' => $type,
            'amount' => $amount,
            'entry_date' => now(),
            'reference' => 'TEST-'.$type->value,
            'notes' => 'Batch test entry',
        ]);
    }
}
