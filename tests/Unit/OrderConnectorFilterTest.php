<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Services\OpenCart\OpenCartHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConnectorFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.oc_mock' => true]);
    }

    public function test_mock_order_api_filters_by_status_ids_only(): void
    {
        $connection = Connection::getInstance();
        $connection->update([
            'store_url' => 'https://example.com',
            'api_token' => 'test-token',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'is_active' => true,
        ]);

        $client = new OpenCartHttpClient($connection->fresh());

        $all = $client->get($connection->order_api_endpoint, []);
        $this->assertCount(3, $all['orders']);

        $filtered = $client->get($connection->order_api_endpoint, ['status_ids' => [2]]);
        $this->assertCount(1, $filtered['orders']);
        $this->assertSame('10045', (string) ($filtered['orders'][0]['source_order_id'] ?? ''));

        $none = $client->get($connection->order_api_endpoint, ['status_ids' => [999]]);
        $this->assertSame([], $none['orders']);

        $empty = $client->get($connection->order_api_endpoint, ['status_ids' => []]);
        $this->assertSame([], $empty['orders']);
    }

    public function test_order_load_params_do_not_include_warehouse_flags(): void
    {
        $service = new \App\Services\OpenCart\OrderSyncService(
            $this->createMock(\App\Services\OpenCart\OpenCartHttpClient::class),
            app(\App\Services\OpenCart\OrderStatusService::class),
            app(\App\Services\OrderMap\OrderMapProductMatcher::class),
            app(\App\Services\OrderMap\OrderMapStockService::class),
            app(\App\Services\OrderMap\OrderMapLoadLogService::class),
            app(\App\Services\OperationalDefaultsService::class),
        );

        $reflection = new \ReflectionClass($service);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringContainsString('fetchOrdersByStatusIds', $source);
        $this->assertStringContainsString('importTrigger()', $source);
        $this->assertStringNotContainsString('from_warehouse', $source);
        $this->assertStringNotContainsString("'supplier_filter' =>", $source);
        $this->assertStringNotContainsString("'since' =>", $source);
    }
}
