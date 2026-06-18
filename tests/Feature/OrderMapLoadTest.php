<?php

namespace Tests\Feature;

use App\Enums\OrderSyncRole;
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

        $this->createImportMapping(2, 'Processing', SfmOrderStatus::New);
    }

    protected function createImportMapping(int $statusId, string $name, SfmOrderStatus $ibs): OrderStatusMapping
    {
        return OrderStatusMapping::query()->create([
            'source_status_id' => $statusId,
            'source_status_name' => $name,
            'sfm_status' => $ibs,
            'sync_role' => OrderSyncRole::ImportTrigger,
            'oc_selected' => true,
        ]);
    }

    protected function createUpdateMapping(int $statusId, string $name, SfmOrderStatus $ibs): OrderStatusMapping
    {
        return OrderStatusMapping::query()->create([
            'source_status_id' => $statusId,
            'source_status_name' => $name,
            'sfm_status' => $ibs,
            'sync_role' => OrderSyncRole::UpdateExisting,
            'oc_selected' => true,
        ]);
    }

    public function test_load_summary_includes_pagination_fields(): void
    {
        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $this->assertArrayHasKey('pages_fetched', $result);
        $this->assertArrayHasKey('connector_total', $result);
        $this->assertGreaterThanOrEqual(1, $result['pages_fetched']);
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

    public function test_load_new_only_fetches_import_trigger_statuses(): void
    {
        $this->createUpdateMapping(5, 'Complete', SfmOrderStatus::Completed);

        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['imported']);
        $this->assertNull(Order::query()->where('source_order_id', '10052')->first());
    }

    public function test_load_new_passes_only_import_trigger_status_ids_to_connector(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'sfm_status' => SfmOrderStatus::New,
            'sync_role' => OrderSyncRole::ImportTrigger,
            'oc_selected' => true,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 5,
            'source_status_name' => 'Complete',
            'sfm_status' => SfmOrderStatus::Completed,
            'sync_role' => OrderSyncRole::UpdateExisting,
            'oc_selected' => true,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 7,
            'source_status_name' => 'Canceled',
            'sfm_status' => SfmOrderStatus::Rejected,
            'sync_role' => OrderSyncRole::UpdateExisting,
            'oc_selected' => true,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 99,
            'source_status_name' => 'Invalid Import',
            'sfm_status' => SfmOrderStatus::Completed,
            'sync_role' => OrderSyncRole::ImportTrigger,
            'oc_selected' => true,
        ]);

        $capturedParams = null;

        $mockClient = $this->mock(\App\Services\OpenCart\OpenCartHttpClient::class, function ($mock) use (&$capturedParams) {
            $mock->shouldReceive('get')
                ->once()
                ->withArgs(function ($endpoint, $params) use (&$capturedParams) {
                    $capturedParams = $params;

                    return is_array($params['status_ids'] ?? null);
                })
                ->andReturn(['orders' => []]);
        });

        $service = new OrderSyncService(
            $mockClient,
            app(\App\Services\OpenCart\OrderStatusService::class),
            app(\App\Services\OrderMap\OrderMapProductMatcher::class),
            app(\App\Services\OrderMap\OrderMapStockService::class),
            app(\App\Services\OrderMap\OrderMapLoadLogService::class),
            app(\App\Services\OperationalDefaultsService::class),
        );

        $service->loadNewOrders($this->adminUser('order-map'));

        $this->assertIsArray($capturedParams);
        $expected = [2, 25];
        sort($expected);
        $actual = $capturedParams['status_ids'];
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_sync_updates_passes_only_update_existing_status_ids_to_connector(): void
    {
        OrderStatusMapping::query()->where('source_status_id', 2)->delete();

        $this->createUpdateMapping(5, 'Complete', SfmOrderStatus::Completed);
        $this->createUpdateMapping(7, 'Canceled', SfmOrderStatus::Rejected);

        $capturedParams = null;

        $mockClient = $this->mock(\App\Services\OpenCart\OpenCartHttpClient::class, function ($mock) use (&$capturedParams) {
            $mock->shouldReceive('get')
                ->once()
                ->withArgs(function ($endpoint, $params) use (&$capturedParams) {
                    $capturedParams = $params;

                    return is_array($params['status_ids'] ?? null);
                })
                ->andReturn(['orders' => []]);
        });

        $service = new OrderSyncService(
            $mockClient,
            app(\App\Services\OpenCart\OrderStatusService::class),
            app(\App\Services\OrderMap\OrderMapProductMatcher::class),
            app(\App\Services\OrderMap\OrderMapStockService::class),
            app(\App\Services\OrderMap\OrderMapLoadLogService::class),
            app(\App\Services\OperationalDefaultsService::class),
        );

        $service->syncStatusUpdates($this->adminUser('order-map'));

        $expected = [5, 7];
        sort($expected);
        $actual = $capturedParams['status_ids'];
        sort($actual);
        $this->assertSame($expected, $actual);
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

        $service->loadNewOrders($user);

        Order::query()->where('source_order_id', '10045')->update([
            'sfm_status' => SfmOrderStatus::Dispatched,
        ]);

        $result = $service->loadNewOrders($user);

        $this->assertSame(0, $result['imported']);
        $this->assertGreaterThanOrEqual(1, $result['duplicates_skipped']);
    }

    public function test_duplicate_new_mapping_does_not_reimport_existing_order(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->loadNewOrders($user);
        $this->assertSame(1, Order::query()->where('source_order_id', '10045')->count());

        $result = $service->loadNewOrders($user);

        $this->assertSame(0, $result['imported']);
        $this->assertGreaterThanOrEqual(1, $result['duplicates_skipped']);
        $this->assertSame(1, Order::query()->where('source_order_id', '10045')->count());
    }

    public function test_update_existing_mapping_does_not_create_new_order_on_load(): void
    {
        $this->createUpdateMapping(5, 'Complete', SfmOrderStatus::Completed);

        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $this->assertNull(Order::query()->where('source_order_id', '10052')->first());
        $this->assertSame(1, $result['fetched']);
    }

    public function test_sync_status_updates_updates_existing_order(): void
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

        $this->createUpdateMapping(5, 'Complete', SfmOrderStatus::Completed);

        $result = app(OrderSyncService::class)->syncStatusUpdates($user);

        $order = Order::query()->where('source_order_id', '10052')->firstOrFail();
        $this->assertSame(SfmOrderStatus::Completed, $order->sfm_status);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['fetched']);
    }

    public function test_sync_status_updates_skips_missing_order(): void
    {
        $this->createUpdateMapping(5, 'Complete', SfmOrderStatus::Completed);

        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->syncStatusUpdates($user);

        $this->assertSame(0, $result['updated']);
        $this->assertGreaterThanOrEqual(1, $result['not_found_skipped']);
        $this->assertNotEmpty($result['skip_log']);
        $this->assertStringContainsString('order not found', strtolower($result['skip_log'][0]['detail']));
    }

    public function test_import_succeeds_when_stock_insufficient(): void
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
            'ibs_stock' => 0,
        ]);

        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $this->assertSame(1, $result['imported']);
        $this->assertNotNull(Order::query()->where('source_order_id', '10045')->first());
    }

    public function test_load_summary_reports_fetched_and_imported_counts(): void
    {
        $user = $this->adminUser('order-map');

        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $this->assertArrayHasKey('fetched', $result);
        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('duplicates_skipped', $result);
        $this->assertArrayHasKey('update_only_skipped', $result);
        $this->assertArrayHasKey('unmatched_lines', $result);
        $this->assertArrayHasKey('requested_status_ids', $result);
        $this->assertArrayHasKey('connector_orders', $result);
        $this->assertArrayHasKey('connector_raw_count', $result);
        $this->assertGreaterThanOrEqual(1, $result['fetched']);
        $this->assertSame($result['imported'], Order::query()->count());
    }

    public function test_order_31475_with_unmapped_products_imports_without_skip_or_stock_move(): void
    {
        OrderStatusMapping::query()->where('source_status_id', 2)->delete();
        $this->createImportMapping(25, 'From Warehouse', SfmOrderStatus::New);

        $mockClient = $this->mock(\App\Services\OpenCart\OpenCartHttpClient::class, function ($mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn([
                    'orders' => [[
                        'source_order_id' => '31475',
                        'order_status_id' => 25,
                        'current_oc_status_id' => 25,
                        'current_oc_status' => 'From Warehouse',
                        'customer_name' => 'Warehouse Customer',
                        'customer_phone' => '+8801711111111',
                        'customer_address' => 'Dhaka',
                        'sale_amount' => 500,
                        'items' => [[
                            'source_product_id' => '99999',
                            'product_name' => 'Unmapped Product',
                            'model' => 'UNK-001',
                            'quantity' => 1,
                            'sale_price' => 500,
                        ]],
                    ]],
                ]);
        });

        $service = new OrderSyncService(
            $mockClient,
            app(\App\Services\OpenCart\OrderStatusService::class),
            app(\App\Services\OrderMap\OrderMapProductMatcher::class),
            app(\App\Services\OrderMap\OrderMapStockService::class),
            app(\App\Services\OrderMap\OrderMapLoadLogService::class),
            app(\App\Services\OperationalDefaultsService::class),
        );

        $result = $service->loadNewOrders($this->adminUser('order-map'));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['fetched']);
        $this->assertSame([25], $result['requested_status_ids']);
        $this->assertSame([], array_values(array_filter(
            $result['skip_log'],
            fn (array $entry) => ($entry['order_id'] ?? null) === '31475'
        )));

        $order = Order::query()->where('source_order_id', '31475')->firstOrFail();
        $this->assertSame(25, $order->current_oc_status_id);
        $this->assertTrue($order->items()->where('is_unmatched', true)->exists());
        $this->assertSame(0.0, (float) $order->items()->sum('supplier_product_cost_snapshot'));
        $this->assertFalse((bool) $order->fresh()->stock_deducted);
        $this->assertSame(0, StockAdjustmentHistory::query()->count());
    }

    public function test_load_new_hides_debug_panel_from_main_queue(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->loadNewOrders($user);
        $service->loadNewOrders($user);

        $this->actingAs($user)
            ->get(route('order-map.index'))
            ->assertOk()
            ->assertDontSee('order-map-sync-panel', false);
    }

    public function test_duplicate_load_records_duplicates_skipped(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->loadNewOrders($user);
        $result = $service->loadNewOrders($user);

        $this->assertSame(0, $result['imported']);
        $this->assertGreaterThanOrEqual(1, $result['duplicates_skipped']);
        $this->assertArrayHasKey('oc_status_id', $result['skip_log'][0]);
        $this->assertNotEmpty($result['skip_log']);
    }

    public function test_import_banner_includes_all_summary_fields(): void
    {
        $user = $this->adminUser('order-map');
        $result = app(OrderSyncService::class)->loadNewOrders($user);

        $message = app(\App\Services\OrderMap\OrderMapLoadLogService::class)->formatBannerMessage($result);

        $this->assertStringContainsString('Fetched from LK:', $message);
        $this->assertStringContainsString('Update-only skipped:', $message);
        $this->assertStringContainsString('Unmatched product lines:', $message);
        $this->assertStringContainsString('status_ids:', $message);
    }

    public function test_accepted_order_is_duplicate_skipped_on_load_new(): void
    {
        $user = $this->adminUser('order-map');
        $service = app(OrderSyncService::class);

        $service->loadNewOrders($user);

        Order::query()->where('source_order_id', '10045')->update([
            'sfm_status' => SfmOrderStatus::Accepted,
        ]);

        $result = $service->loadNewOrders($user);

        $this->assertSame(0, $result['imported']);
        $this->assertGreaterThanOrEqual(1, $result['duplicates_skipped']);
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

        app(OrderSyncService::class)->loadNewOrders($user);

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

        app(OrderSyncService::class)->loadNewOrders($user);

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
