<?php

namespace Tests\Unit;

use App\Models\OrderStatusMapping;
use App\Services\OpenCart\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_queue_statuses_reads_status_id_name_and_selected(): void
    {
        $service = app(OrderStatusService::class);

        $parsed = $service->parseStatusesFromResponse([
            'success' => true,
            'queue_statuses' => [
                ['status_id' => '25', 'name' => 'From Warehouse', 'selected' => true],
                ['status_id' => '7', 'name' => 'Canceled', 'selected' => true],
                ['status_id' => '2', 'name' => 'Processing', 'selected' => false],
            ],
        ]);

        $this->assertCount(3, $parsed);
        $this->assertSame(25, $parsed[0]['id']);
        $this->assertSame('From Warehouse', $parsed[0]['name']);
        $this->assertTrue($parsed[0]['selected']);
        $this->assertFalse($parsed[2]['selected']);
    }

    public function test_fetch_from_opencart_persists_selected_connector_statuses(): void
    {
        config([
            'dropflow.oc_mock' => true,
            'dropflow.allow_opencart_sync' => true,
        ]);

        \App\Models\Connection::getInstance()->update([
            'store_url' => 'https://example.com',
            'api_token' => 'test-token',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'is_active' => true,
        ]);

        $result = app(OrderStatusService::class)->fetchFromOpenCart();

        $this->assertSame(7, $result['total']);
        $this->assertSame(5, $result['selected']);

        $this->assertDatabaseHas('order_status_mappings', [
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'oc_selected' => 1,
        ]);

        $this->assertDatabaseHas('order_status_mappings', [
            'source_status_id' => 7,
            'source_status_name' => 'Canceled',
            'oc_selected' => 1,
        ]);

        $this->assertDatabaseHas('order_status_mappings', [
            'source_status_id' => 2,
            'source_status_name' => 'Processing',
            'oc_selected' => 0,
        ]);

        $this->assertSame(
            5,
            OrderStatusMapping::query()->where('oc_selected', true)->count()
        );
    }
}
