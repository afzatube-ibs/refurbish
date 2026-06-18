<?php

namespace Tests\Feature;

use App\Enums\DispatchBatchItemCostStatus;
use App\Enums\OrderItemStatus;
use App\Enums\SfmOrderStatus;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\DispatchBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\DispatchBatchService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchBatchReportTest extends TestCase
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
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'dispatch-batch-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        Connection::getInstance()->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'is_active' => true,
        ]);
    }

    public function test_dispatch_report_list_loads(): void
    {
        $this->createPackedOrder('LIST-1', 100, 2);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch'))
            ->assertOk()
            ->assertSee('Dispatch Report', false)
            ->assertSee('Dispatch Batches', false)
            ->assertSee('Total Batches', false)
            ->assertSee('Dispatched Orders', false);
    }

    public function test_packed_orders_can_create_dispatch_batch(): void
    {
        $orderA = $this->createPackedOrder('BAT-1', 50, 1);
        $orderB = $this->createPackedOrder('BAT-2', 75, 2);

        $response = $this->actingAs($this->supplierUser)->post(route('reports.dispatch.create-batch'), [
            'order_ids' => [$orderA->id, $orderB->id],
            'dispatch_date' => '2026-06-19',
            'orders' => [
                $orderA->id => ['consignment_id' => 'CN-A', 'courier' => 'CourierX'],
                $orderB->id => ['consignment_id' => 'CN-B', 'courier' => 'CourierX'],
            ],
        ]);

        $batch = DispatchBatch::query()->first();
        $this->assertNotNull($batch);
        $response->assertRedirect(route('reports.dispatch.show', $batch));

        $this->assertDatabaseHas('dispatch_batches', [
            'batch_no' => '19062026-01',
            'total_orders' => 2,
            'total_qty' => 3,
            'total_supplier_cost' => 200.00,
        ]);
    }

    public function test_non_packed_orders_cannot_be_batched(): void
    {
        $order = $this->createPackedOrder('ACCEPT-1', 40, 1);
        $order->update(['sfm_status' => SfmOrderStatus::Accepted]);

        $this->actingAs($this->supplierUser)
            ->post(route('reports.dispatch.create-batch'), [
                'order_ids' => [$order->id],
                'dispatch_date' => '2026-06-19',
                'orders' => [
                    $order->id => ['consignment_id' => 'CN-1'],
                ],
            ])
            ->assertRedirect(route('order-map.index', ['status' => 'packed']))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('dispatch_batches', 0);
    }

    public function test_same_order_cannot_be_batched_twice(): void
    {
        $order = $this->createPackedOrder('DUP-1', 60, 1);

        $payload = [
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-19',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-DUP'],
            ],
        ];

        $this->actingAs($this->supplierUser)->post(route('reports.dispatch.create-batch'), $payload);
        $this->assertDatabaseCount('dispatch_batches', 1);

        $order2 = $this->createPackedOrder('DUP-2', 30, 1);
        $this->actingAs($this->supplierUser)
            ->post(route('reports.dispatch.create-batch'), [
                'order_ids' => [$order->id, $order2->id],
                'dispatch_date' => '2026-06-19',
                'orders' => [
                    $order->id => ['consignment_id' => 'CN-AGAIN'],
                    $order2->id => ['consignment_id' => 'CN-2'],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('dispatch_batches', 1);
    }

    public function test_batch_totals_orders_qty_and_supplier_cost(): void
    {
        $orderA = $this->createPackedOrder('TOT-1', 120, 2);
        $orderB = $this->createPackedOrder('TOT-2', 0, 3, missingCost: true);

        $batch = app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$orderA->id, $orderB->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $orderA->id => ['consignment_id' => 'CN-T1', 'courier' => 'DHL'],
                $orderB->id => ['consignment_id' => 'CN-T2'],
            ],
        ], $this->supplierUser);

        $this->assertSame(2, $batch->total_orders);
        $this->assertSame(5, $batch->total_qty);
        $this->assertSame(240.0, (float) $batch->total_supplier_cost);
    }

    public function test_missing_supplier_cost_shows_missing_cost_status(): void
    {
        $order = $this->createPackedOrder('MISS-1', 0, 1, missingCost: true);

        $batch = app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-MISS'],
            ],
        ], $this->supplierUser);

        $this->assertDatabaseHas('dispatch_batch_items', [
            'dispatch_batch_id' => $batch->id,
            'cost_status' => DispatchBatchItemCostStatus::MissingCost->value,
            'supplier_unit_cost' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch.show', $batch))
            ->assertOk()
            ->assertSee('Missing cost', false);
    }

    public function test_created_batch_marks_orders_dispatched(): void
    {
        $order = $this->createPackedOrder('DISP-1', 45, 2);

        app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-DISP', 'courier' => 'FedEx'],
            ],
        ], $this->supplierUser);

        $order->refresh();
        $this->assertSame(SfmOrderStatus::Dispatched, $order->sfm_status);
        $this->assertSame('CN-DISP', $order->consignment_id);
    }

    public function test_batch_detail_shows_included_orders_and_products(): void
    {
        $order = $this->createPackedOrder('DET-1', 88, 1, productName: 'Widget Pro');

        $batch = app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-DET'],
            ],
        ], $this->supplierUser);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch.show', $batch))
            ->assertOk()
            ->assertSee($batch->batch_no, false)
            ->assertSee('DET-1', false)
            ->assertSee('Widget Pro', false)
            ->assertSee('Dispatched Orders', false);
    }

    public function test_filters_by_date_supplier_store_and_search(): void
    {
        $order = $this->createPackedOrder('SRCH-99', 25, 1);

        $batch = app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-18',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-SRCH', 'courier' => 'BlueDart'],
            ],
        ], $this->supplierUser);

        $connectionId = Connection::getInstance()->id;

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch', [
                'from' => '2026-06-18',
                'to' => '2026-06-18',
                'supplier_id' => $this->supplier->id,
                'connection_id' => $connectionId,
                'search' => 'SRCH-99',
            ]))
            ->assertOk()
            ->assertSee($batch->batch_no, false);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch', ['courier' => 'BlueDart']))
            ->assertOk()
            ->assertSee($batch->batch_no, false);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch', ['search' => 'NO-MATCH']))
            ->assertOk()
            ->assertDontSee($batch->batch_no, false);
    }

    public function test_print_route_loads(): void
    {
        $order = $this->createPackedOrder('PRT-1', 33, 1);

        $batch = app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$order->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $order->id => ['consignment_id' => 'CN-PRT'],
            ],
        ], $this->supplierUser);

        $this->actingAs($this->admin)
            ->get(route('reports.dispatch.print', $batch))
            ->assertOk()
            ->assertSee('Dispatch Batch '.$batch->batch_no, false)
            ->assertSee('PRT-1', false);
    }

    public function test_batch_creation_posts_ledger_entries_per_order(): void
    {
        $orderA = $this->createPackedOrder('LED-1', 40, 1);
        $orderB = $this->createPackedOrder('LED-2', 60, 1);

        app(DispatchBatchService::class)->createFromPackedOrders([
            'order_ids' => [$orderA->id, $orderB->id],
            'dispatch_date' => '2026-06-20',
            'orders' => [
                $orderA->id => ['consignment_id' => 'CN-L1'],
                $orderB->id => ['consignment_id' => 'CN-L2'],
            ],
        ], $this->supplierUser);

        $this->assertDatabaseCount('supplier_ledger_entries', 2);
        $this->assertDatabaseHas('supplier_ledger_entries', ['order_id' => $orderA->id]);
        $this->assertDatabaseHas('supplier_ledger_entries', ['order_id' => $orderB->id]);
    }

    protected function createPackedOrder(
        string $sourceOrderId,
        float $unitCost,
        int $qty,
        bool $missingCost = false,
        string $productName = 'Test Product',
    ): Order {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => $sourceOrderId,
            'customer_name' => 'Customer '.$sourceOrderId,
            'customer_phone' => '017'.substr(md5($sourceOrderId), 0, 8),
            'customer_address' => 'Dhaka',
            'sale_amount' => 0,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => SfmOrderStatus::Packed,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '9001',
            'product_name' => $productName,
            'model' => 'M-'.$sourceOrderId,
            'quantity' => $qty,
            'sale_price' => 0,
            'supplier_product_cost_snapshot' => $missingCost ? null : $unitCost,
            'item_status' => OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);

        return $order->fresh('items');
    }
}
