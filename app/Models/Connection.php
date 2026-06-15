<?php

namespace App\Models;

use App\Services\OpenCart\IbsRouteResolver;
use Illuminate\Database\Eloquent\Model;

class Connection extends Model
{
    protected $fillable = [
        'store_url',
        'api_token',
        'product_api_endpoint',
        'order_api_endpoint',
        'order_status_api_endpoint',
        'supplier_filter',
        'is_active',
        'product_sync_page',
        'last_product_sync_at',
        'last_order_sync_at',
        'last_connection_test_at',
        'last_connection_test_status',
        'last_connection_test_message',
        'last_connector_version',
        'last_option_image_summary',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'is_active' => 'boolean',
            'product_sync_page' => 'integer',
            'last_product_sync_at' => 'datetime',
            'last_order_sync_at' => 'datetime',
            'last_connection_test_at' => 'datetime',
        ];
    }

    public static function getInstance(): self
    {
        $defaults = IbsRouteResolver::defaultFormEndpoints();

        return static::query()->firstOrCreate(
            [],
            [
                'store_url' => '',
                'api_token' => '',
                'product_api_endpoint' => $defaults['product_api_endpoint'],
                'order_api_endpoint' => $defaults['order_api_endpoint'],
                'order_status_api_endpoint' => $defaults['order_status_api_endpoint'],
                'supplier_filter' => 'ex-a',
                'is_active' => false,
                'product_sync_page' => 1,
            ]
        );
    }
}
