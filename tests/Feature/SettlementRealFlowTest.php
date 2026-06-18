<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\SettlementEntryType;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayableService;
use App\Services\SettlementService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\Concerns\SeedsProductMapCatalog;
use Tests\TestCase;

class SettlementRealFlowTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;
    use SeedsProductMapCatalog;

    private const UNIT_COST = 120.0;

    private const QUANTITY = 2;

    private const SETTLEMENT_PAID = 25.0;

    private User $admin;

    private User $supplierUser;

    private Supplier $supplier;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true, 'dropflow.modules.product_map' => true]);

        $this->seed(SupplierSeeder::class);
        $this->supplier = Supplier::query()->firstOrFail();
        $this->supplierUser = User::query()->where('email', 'supplier@ex-a.com')->firstOrFail();
        $this->admin = $this->adminUser('settlement-real-flow');

        Connection::getInstance()->update([
            'is_active' => true,
            'store_url' => 'https://www.staging.lokkisona.com',
            'supplier_filter' => 'ex-a',
        ]);

        $this->seedProductMapCatalog();
        ProductControlState::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_product_id' => '9509',
            'ibs_model' => 'IBS-9509',
            'rate' => self::UNIT_COST,
        ]);

        $this->actingAs($this->admin)->post(route('order-map.store'), [
            'source_store' => 'lokkisona',
            'source_type' => 'phone',
            'customer_name' => 'Settlement Flow Customer',
            'customer_phone' => '+8801700000123',
            'customer_address' => '12 Test Road, Dhaka',
            'items' => [
                [
                    'source_product_id' => '9509',
                    'product_name' => 'Catalog Product',
                    'model' => 'PARENT-9509',
                    'quantity' => self::QUANTITY,
                    'sale_price' => 300,
                ],
            ],
        ])->assertRedirect(route('order-map.index'));

        $this->order = Order::query()->where('customer_name', 'Settlement Flow Customer')->firstOrFail();
    }

    public function test_full_settlement_flow_posts_ledger_and_matches_all_payable_views(): void
    {
        $dispatchTotal = self::UNIT_COST * self::QUANTITY;

        $this->actingAs($this->supplierUser)->post(route('order-map.accept', $this->order))->assertRedirect();
        $this->actingAs($this->supplierUser)->post(route('order-map.pack', $this->order))->assertRedirect();
        $this->actingAs($this->supplierUser)->post(route('order-map.dispatch', $this->order), [
            'courier' => 'CourierX',
            'consignment_id' => 'CN-FLOW-1',
        ])->assertRedirect();

        $this->order->refresh();
        $this->assertSame(SfmOrderStatus::Dispatched, $this->order->sfm_status);
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'order_id' => $this->order->id,
            'type' => LedgerEntryType::DispatchCost->value,
            'amount' => $dispatchTotal,
        ]);

        $this->actingAs($this->supplierUser)->post(route('order-map.return-queue', $this->order))->assertRedirect();
        $this->actingAs($this->supplierUser)->post(route('order-map.return-received', $this->order))->assertRedirect();

        $this->assertDatabaseHas('supplier_ledger_entries', [
            'order_id' => $this->order->id,
            'type' => LedgerEntryType::ReturnReversal->value,
            'amount' => -$dispatchTotal,
        ]);

        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::PaidToStoreOwner,
            self::SETTLEMENT_PAID,
            new \DateTimeImmutable('2026-06-10'),
            $this->admin,
            'PAY-FLOW-1',
            'Store owner settlement',
        );

        $this->assertDatabaseCount('supplier_ledger_entries', 3);

        $payableService = app(PayableService::class);
        $summary = $payableService->summary($this->supplier->id);
        $expectedBalance = -self::SETTLEMENT_PAID;

        $this->assertSame($dispatchTotal, $summary['delivered_cost']);
        $this->assertSame($dispatchTotal, $summary['returned_cost']);
        $this->assertSame(self::SETTLEMENT_PAID, $summary['total_paid']);
        $this->assertSame(self::SETTLEMENT_PAID, $summary['paid_to_store_owner']);
        $this->assertSame($expectedBalance, $payableService->closingBalance($summary));
        $this->assertSame($expectedBalance, $summary['net_payable']);

        $closingRow = $payableService->statementClosingRow($this->supplier->id);
        $this->assertNotNull($closingRow);
        $this->assertSame($expectedBalance, $closingRow['running_balance']);

        $this->actingAs($this->admin)
            ->get(route('payables.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee(number_format($dispatchTotal, 2), false)
            ->assertSee(number_format(self::SETTLEMENT_PAID, 2), false)
            ->assertSee(number_format($expectedBalance, 2), false)
            ->assertSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Current Balance', false)
            ->assertSee(number_format($expectedBalance, 2), false)
            ->assertDontSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Account Statement', false)
            ->assertSee(number_format($expectedBalance, 2), false);
    }
}
