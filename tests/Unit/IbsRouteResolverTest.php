<?php

namespace Tests\Unit;

use App\Services\OpenCart\IbsRouteResolver;
use PHPUnit\Framework\TestCase;

class IbsRouteResolverTest extends TestCase
{
    public function test_legacy_dropflow_routes_map_to_ibs(): void
    {
        $this->assertSame(
            'index.php?route=api/ibs/products',
            IbsRouteResolver::normalizeStoredEndpoint(
                'index.php?route=extension/dropflow/products',
                'products'
            )
        );

        $this->assertSame(
            'index.php?route=api/ibs/order_queue_statuses',
            IbsRouteResolver::normalizeStoredEndpoint(
                'index.php?route=extension/dropflow/order_statuses',
                'order_queue_statuses'
            )
        );
    }

    public function test_bare_ibs_route_builds_index_php_url(): void
    {
        $this->assertSame(
            'index.php?route=api/ibs/orders',
            IbsRouteResolver::toIndexPhpRoute('api/ibs/orders')
        );
    }

    public function test_routes_from_connection_test_body(): void
    {
        $routes = IbsRouteResolver::routesFromConnectionTest([
            'success' => true,
            'connector_version' => '1.1.0',
            'warehouse_product_count' => 42,
            'routes' => [
                'connection_test' => 'api/ibs/connection_test',
                'products' => 'api/ibs/products',
                'orders' => 'api/ibs/orders',
                'order_queue_statuses' => 'api/ibs/order_queue_statuses',
            ],
        ]);

        $this->assertSame('api/ibs/products', $routes['products']);
        $this->assertSame('api/ibs/orders', $routes['orders']);
        $this->assertSame('api/ibs/order_queue_statuses', $routes['order_queue_statuses']);
    }

    public function test_numeric_products_key_is_ignored(): void
    {
        $routes = IbsRouteResolver::routesFromConnectionTest([
            'products' => 42,
            'orders' => 10,
        ]);

        $this->assertSame('api/ibs/products', $routes['products']);
        $this->assertSame('api/ibs/orders', $routes['orders']);
    }
}
