<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderStatusMapping;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductControlVariant;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\Supplier;
use App\Services\OpenCart\OrderSyncService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class OrderMapLoadTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dropflow.modules.order_map' => true,
            'dropflow.oc_mock' => true,
            'dropflow.allow_opencart_sync' => true,
        ]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update([
            'store_url' => 'https://example.com',
            'api_token' => 'test-token',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 2,
            'source_status_name' => 'Processing',
            'sfm_status' => SfmOrderStatus::New,
            'oc_selected' => true,
        ]);
    }

    public function test_mapped_status_imports_as_ibs_new(): void
    {
        $user = $this->adminUser('order-map');

        $this->actingAs($user)
            ->post(route('order-map.load'))
            ->assertRedirect(route('order-map.index'));

        $order = Order::query()->where('source_order_id', '10045')->first();
        $this->assertNotNull($order);
        $this->assertSame(SfmOrderStatus::New, $order->sfm_status);
    }

    public function test_import_does_not_require_product_map_match(): void
    {
        $user = $this->adminUser('order-map');

        $this->actingAs($user)->post(route('order-map.load'));

        $order = Order::query()->where('source_order_id', '10045')->firstOrFail();
        $this->assertTrue($order->items()->where('is_unmatched', true)->exists());
        $this->assertCount(2, $order->items);
    }

    public function test_unmapped_oc_status_is_skipped(): void
    {
        $user = $this->adminUser('order-map');

        $this->actingAs($user)->post(route('order-map.load'));

        $this->assertNull(Order::query()->where('source_order_id', '10099')->first());
    }

    public function test_duplicate_import_skipped_when_status_locked(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->load($user);

        Order::query()->where('source_order_id', '10045')->update([
            'sfm_status' => SfmOrderStatus::Dispatched,
        ]);

        $result = $service->load($user);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    public function test_duplicate_new_mapping_does_not_reimport_existing_order(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->load($user);
        $this->assertSame(1, Order::query()->where('source_order_id', '10045')->count());

        $result = $service->load($user);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame(1, Order::query()->where('source_order_id', '10045')->count());
    }

    public function test_completed_mapping_does_not_create_new_order(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 5,
            'source_status_name' => 'Complete',
            'sfm_status' => SfmOrderStatus::Completed,
            'oc_selected' => true,
        ]);

        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->load($user);

        $this->assertNull(Order::query()->where('source_order_id', '10052')->first());
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    public function test_completed_mapping_syncs_existing_order_status(): void
    {
        $user = $this->adminUser('order-map');
        $supplier = Supplier::query()->where('is_active', true)->firstOrFail();

        Order::query()->create([
            'supplier_id' => $supplier->id,
            'source_order_id' => '10052',
            'customer_name' => 'Existing Customer',
            'customer_phone' => '+8801700000099',
            'customer_address' => 'Existing',
            'sale_amount' => 1200,
            'current_oc_status' => 'Shipped',
            'sfm_status' => SfmOrderStatus::Dispatched,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 5,
            'source_status_name' => 'Complete',
            'sfm_status' => SfmOrderStatus::Completed,
            'oc_selected' => true,
        ]);

        $result = app(OrderSyncService::class)->load($user);

        $order = Order::query()->where('source_order_id', '10052')->firstOrFail();
        $this->assertSame(SfmOrderStatus::Completed, $order->sfm_status);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['imported']);
    }

    public function test_duplicate_import_updates_when_accepted(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->load($user);

        Order::query()->where('source_order_id', '10045')->update([
            'sfm_status' => SfmOrderStatus::Accepted,
        ]);

        $result = $service->load($user);

        $this->assertSame(1, $result['updated']);
    }

    public function test_stock_deducts_at_new_import_when_product_matched(): void
    {
        $user = $this->adminUser('order-map');
        $supplier = Supplier::query()->where('is_active', true)->firstOrFail();

        $state = ProductControlState::query()->create([
            'supplier_id' => $supplier->id,
            'source_product_id' => '101',
            'rate' => 99.50,
        ]);

        ProductControlVariant::query()->create([
            'product_control_state_id' => $state->id,
            'source_variant_key' => 'EXA-WH-001',
            'ibs_stock' => 10,
        ]);

        app(OrderSyncService::class)->load($user);

        $variant = ProductControlVariant::query()->where('source_variant_key', 'EXA-WH-001')->firstOrFail();
        $this->assertSame(9, (int) $variant->ibs_stock);

        $order = Order::query()->where('source_order_id', '10045')->firstOrFail();
        $this->assertTrue($order->stock_deducted);

        $this->assertDatabaseHas('stock_adjustment_history', [
            'product_id' => '101',
            'difference' => -1,
        ]);
    }

    public function test_stock_restores_when_order_rejected(): void
    {
        $user = $this->adminUser('order-map');
        $supplier = Supplier::query()->where('is_active', true)->firstOrFail();

        $state = ProductControlState::query()->create([
            'supplier_id' => $supplier->id,
            'source_product_id' => '101',
            'rate' => 99.50,
        ]);

        ProductControlVariant::query()->create([
            'product_control_state_id' => $state->id,
            'source_variant_key' => 'EXA-WH-001',
            'ibs_stock' => 10,
        ]);

        app(OrderSyncService::class)->load($user);

        $order = Order::query()->where('source_order_id', '10045')->firstOrFail();

        app(\App\Services\OrderWorkflowService::class)->reject($order, $user);

        $variant = ProductControlVariant::query()->where('source_variant_key', 'EXA-WH-001')->firstOrFail();
        $this->assertSame(10, (int) $variant->ibs_stock);
        $this->assertFalse($order->fresh()->stock_deducted);

        $this->assertSame(
            2,
            StockAdjustmentHistory::query()->where('product_id', '101')->count()
        );
    }
}
