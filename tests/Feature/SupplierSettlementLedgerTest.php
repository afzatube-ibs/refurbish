<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\OrderItemStatus;
use App\Enums\ReturnStatus;
use App\Enums\SfmOrderStatus;
use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\DispatchReport;
use App\Models\DispatchReportItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnModel;
use App\Models\ReturnItem;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\DispatchService;
use App\Services\PayableService;
use App\Services\ReturnService;
use App\Services\SettlementService;
use App\Services\SupplierLedgerService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupplierSettlementLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        $this->supplier = Supplier::query()->firstOrFail();
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'ledger-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_dispatch_posts_ledger_entry_once(): void
    {
        $order = $this->createOrderWithItem(cost: 100, qty: 2);

        app(DispatchService::class)->create($order, 'Courier', 'CN-1', null, $this->admin->id);

        $this->assertDatabaseCount('supplier_ledger_entries', 1);
        $entry = SupplierLedgerEntry::query()->first();
        $this->assertSame(LedgerEntryType::DispatchCost, $entry->type);
        $this->assertSame(-200.0, (float) $entry->amount);
        $this->assertSame($order->id, $entry->order_id);

        $report = DispatchReport::query()->firstOrFail();
        app(SupplierLedgerService::class)->postDispatch($report);
        $this->assertDatabaseCount('supplier_ledger_entries', 1);
    }

    public function test_return_confirm_posts_reversal_entry(): void
    {
        $order = $this->createOrderWithItem(cost: 50, qty: 1, status: SfmOrderStatus::ReturnQueue);
        $order->items()->first()->update([
            'supplier_product_cost_snapshot' => 50,
            'item_status' => OrderItemStatus::ReturnPending,
        ]);

        $return = ReturnModel::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $this->supplier->id,
            'return_status' => ReturnStatus::Pending,
        ]);

        ReturnItem::query()->create([
            'return_id' => $return->id,
            'order_item_id' => $order->items()->first()->id,
            'quantity' => 1,
            'supplier_cost_snapshot' => 50,
        ]);

        app(ReturnService::class)->confirmReceived($return, $this->admin);

        $entry = SupplierLedgerEntry::query()->first();
        $this->assertSame(LedgerEntryType::ReturnReversal, $entry->type);
        $this->assertSame(50.0, (float) $entry->amount);

        app(SupplierLedgerService::class)->postReturnReversal($return->fresh(['returnItems']));
        $this->assertDatabaseCount('supplier_ledger_entries', 1);
    }

    public function test_settlement_ledger_posting_is_not_duplicated(): void
    {
        $entry = app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::PaidToStoreOwner,
            30,
            new \DateTimeImmutable('2026-06-03'),
            $this->admin,
            'PAY-DUP',
        );

        app(SupplierLedgerService::class)->postSettlement($entry);
        $this->assertDatabaseCount('supplier_ledger_entries', 1);

        app(SupplierLedgerService::class)->postSettlement($entry->fresh());
        $this->assertDatabaseCount('supplier_ledger_entries', 1);
    }

    public function test_closing_balance_matches_account_statement(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            40,
            new \DateTimeImmutable('2026-06-04'),
            $this->admin,
        );

        $payableService = app(PayableService::class);
        $summary = $payableService->summary($this->supplier->id);
        $closingRow = $payableService->statementClosingRow($this->supplier->id);

        $this->assertNotNull($closingRow);
        $this->assertSame($payableService->closingBalance($summary), $closingRow['running_balance']);
        $this->assertSame($summary['net_payable'], $closingRow['running_balance']);
    }

    public function test_settlement_entry_posts_to_ledger_and_updates_payables(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            75,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
            'TRX-1',
            'COD remittance',
        );

        $this->assertDatabaseHas('settlement_entries', [
            'entry_type' => SettlementEntryType::ReceivedFromSupplier->value,
            'amount' => 75,
        ]);

        $ledger = SupplierLedgerEntry::query()->first();
        $this->assertSame(LedgerEntryType::ReceivedFromSupplier, $ledger->type);
        $this->assertSame(75.0, (float) $ledger->amount);

        $summary = app(PayableService::class)->summary($this->supplier->id);
        $this->assertSame(75.0, $summary['total_paid']);
        $this->assertSame(75.0, $summary['net_payable']);
    }

    public function test_payables_page_and_account_statement_render(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::PaidToStoreOwner,
            40,
            new \DateTimeImmutable('2026-06-02'),
            $this->admin,
            'PAY-1',
        );

        $this->actingAs($this->admin)
            ->get(route('payables.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Total Dispatched Cost')
            ->assertSee('Record Settlement');
    }

    public function test_order_settlement_history_on_detail_panel(): void
    {
        $order = $this->createOrderWithItem(cost: 30, qty: 1);
        app(DispatchService::class)->create($order, 'Courier', 'CN-99', null, $this->admin->id);

        $this->actingAs($this->admin)
            ->get(route('order-map.panel', $order))
            ->assertOk()
            ->assertSee('Settlement History')
            ->assertSee('Dispatch cost');
    }

    protected function createOrderWithItem(float $cost, int $qty, SfmOrderStatus $status = SfmOrderStatus::Packed): Order
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => 'TST-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_phone' => '01700000000',
            'customer_address' => 'Dhaka',
            'sale_amount' => 0,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => $status,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '9001',
            'product_name' => 'Widget',
            'model' => 'W-1',
            'quantity' => $qty,
            'sale_price' => 0,
            'supplier_product_cost_snapshot' => $cost,
            'item_status' => OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);

        return $order->fresh('items');
    }
}
