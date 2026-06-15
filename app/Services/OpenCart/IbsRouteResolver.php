<?php

namespace App\Services\OpenCart;

class IbsRouteResolver
{
    /** @var array<string, string> */
    public const LEGACY_MAP = [
        'extension/dropflow/products' => 'api/ibs/products',
        'extension/dropflow/orders' => 'api/ibs/orders',
        'extension/dropflow/order_statuses' => 'api/ibs/order_queue_statuses',
    ];

    /**
     * @return array{connection_test: string, products: string, orders: string, order_queue_statuses: string}
     */
    public static function defaultRoutes(): array
    {
        return [
            'connection_test' => 'api/ibs/connection_test',
            'products' => 'api/ibs/products',
            'orders' => 'api/ibs/orders',
            'order_queue_statuses' => 'api/ibs/order_queue_statuses',
        ];
    }

    /**
     * @return array{product_api_endpoint: string, order_api_endpoint: string, order_status_api_endpoint: string}
     */
    public static function defaultFormEndpoints(): array
    {
        $routes = self::defaultRoutes();

        return [
            'product_api_endpoint' => self::toIndexPhpRoute($routes['products']),
            'order_api_endpoint' => self::toIndexPhpRoute($routes['orders']),
            'order_status_api_endpoint' => self::toIndexPhpRoute($routes['order_queue_statuses']),
        ];
    }

    public static function isLegacyDropflowEndpoint(?string $endpoint): bool
    {
        if (! is_string($endpoint) || $endpoint === '') {
            return false;
        }

        return str_contains(strtolower($endpoint), 'extension/dropflow');
    }

    public static function normalizeStoredEndpoint(?string $endpoint, string $routeKey): string
    {
        $defaults = self::defaultRoutes();
        $fallback = $defaults[$routeKey] ?? $defaults['products'];

        if (blank($endpoint) || self::isLegacyDropflowEndpoint($endpoint)) {
            return self::toIndexPhpRoute($fallback);
        }

        return self::toIndexPhpRoute($endpoint);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeConnectionInput(array $data): array
    {
        $data['product_api_endpoint'] = self::normalizeStoredEndpoint(
            $data['product_api_endpoint'] ?? null,
            'products'
        );
        $data['order_api_endpoint'] = self::normalizeStoredEndpoint(
            $data['order_api_endpoint'] ?? null,
            'orders'
        );
        $data['order_status_api_endpoint'] = self::normalizeStoredEndpoint(
            $data['order_status_api_endpoint'] ?? null,
            'order_queue_statuses'
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $connectionTestBody
     * @return array{connection_test: string, products: string, orders: string, order_queue_statuses: string}
     */
    public static function routesFromConnectionTest(array $connectionTestBody): array
    {
        $defaults = self::defaultRoutes();

        return [
            'connection_test' => self::extractRoute($connectionTestBody, [
                'routes.connection_test',
                'connection_test',
                'endpoints.connection_test',
            ], $defaults['connection_test']),
            'products' => self::extractRoute($connectionTestBody, [
                'routes.products',
                'products',
                'endpoints.products',
            ], $defaults['products']),
            'orders' => self::extractRoute($connectionTestBody, [
                'routes.orders',
                'orders',
                'endpoints.orders',
            ], $defaults['orders']),
            'order_queue_statuses' => self::extractRoute($connectionTestBody, [
                'routes.order_queue_statuses',
                'routes.order_statuses',
                'order_queue_statuses',
                'order_statuses',
                'endpoints.order_queue_statuses',
                'endpoints.order_statuses',
            ], $defaults['order_queue_statuses']),
        ];
    }

    public static function toIndexPhpRoute(string $route): string
    {
        $route = self::normalizeRoute($route);

        return 'index.php?route='.$route;
    }

    public static function normalizeRoute(string $route): string
    {
        $route = trim($route);

        if (preg_match('/route=([^&]+)/', $route, $matches)) {
            $route = urldecode($matches[1]);
        }

        $route = ltrim($route, '/');

        foreach (self::LEGACY_MAP as $legacy => $ibs) {
            if (str_contains($route, $legacy)) {
                return $ibs;
            }
        }

        if (str_starts_with($route, 'index.php')) {
            return self::defaultRoutes()['products'];
        }

        return $route;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $paths
     */
    protected static function extractRoute(array $body, array $paths, string $default): string
    {
        foreach ($paths as $path) {
            $value = data_get($body, $path);

            if (! is_string($value) || $value === '') {
                continue;
            }

            if (str_contains($value, 'api/ibs/') || str_contains($value, 'extension/dropflow/')) {
                return self::normalizeRoute($value);
            }
        }

        return $default;
    }
}
