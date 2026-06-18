<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\OrderItemStatus;
use App\Enums\SettlementEntryType;
use App\Enums\SfmOrderStatus;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\DispatchBatchService;
use App\Services\SettlementService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BalanceMeaningUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $supplierUser;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        $this->supplier = Supplier::query()->firstOrFail();
        $this->supplierUser = User::query()->where('email', 'supplier@ex-a.com')->firstOrFail();
        Connection::getInstance()->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'balance-meaning-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_operational_payables_report_positive_means_need_to_pay_supplier(): void
    {
        $this->createDispatchedOrder('POS-1', 200, 1);

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Need to pay supplier', false)
            ->assertDontSee('Record Settlement', false);
    }

    public function test_operational_payables_report_negative_means_overpaid(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            200,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
        );

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Overpaid / review needed', false);
    }

    public function test_operational_payables_report_zero_means_settled(): void
    {
        $this->createDispatchedOrder('ZERO-1', 100, 1);

        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            100,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
        );

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Settled', false);
    }

    public function test_legacy_payables_page_keeps_old_balance_meaning(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            200,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
        );

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Supplier needs to pay Lokkisona', false)
            ->assertSee('Record Settlement', false);
    }

    public function test_legacy_ledger_page_keeps_old_balance_meaning(): void
    {
        $this->postDispatchLedger(-200);

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', $query))
            ->assertOk()
            ->assertSee('Lokkisona needs to pay supplier', false)
            ->assertSee('Account Statement', false);
    }

    public function test_payables_settlement_form_shows_type_helper_text(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payables.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Money received by store owner from supplier or COD collection.', false)
            ->assertSee('Supplier paid or returned money to store owner.', false)
            ->assertSee('Manual correction — amount may be positive or negative.', false);
    }

    public function test_settlement_form_only_on_legacy_operational_payables_page(): void
    {
        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertDontSee('Record Settlement', false)
            ->assertSee('payables-balance-col', false);

        $this->actingAs($this->admin)
            ->get(route('reports.collections', $query))
            ->assertOk()
            ->assertDontSee('Record Settlement', false);
    }

    protected function createDispatchedOrder(string $sourceOrderId, float $unitCost, int $qty): Order
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => $sourceOrderId,
            'customer_name' => 'Customer',
            'customer_phone' => '01700000000',
            'customer_address' => 'Dhaka',
            'sale_amount' => 0,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => SfmOrderStatus::Packed,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '9001',
            'product_name' => 'Test Product',
            'model' => 'M-1',
            'quantity' => $qty,
            'sale_price' => 0,
            'supplier_product_cost_snapshot' => $unitCost,
            'item_status' => OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);

        app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-'.$sourceOrderId],
            ],
        ], $this->supplierUser);

        return $order;
    }

    private function postDispatchLedger(float $amount): void
    {
        $connection = Connection::getInstance();

        SupplierLedgerEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'connection_id' => $connection->id,
            'type' => LedgerEntryType::DispatchCost,
            'amount' => $amount,
            'entry_date' => now(),
            'reference' => 'TEST-DISPATCH',
            'notes' => 'Balance meaning test dispatch',
        ]);
    }
}
