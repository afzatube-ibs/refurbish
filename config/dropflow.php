<?php

return [
    'product_batch_size' => 50,
    'product_preview_target' => (int) env('DROPflow_PRODUCT_PREVIEW_TARGET', 42),
    'product_preview_page_size' => (int) env('DROPflow_PRODUCT_PREVIEW_PAGE_SIZE', 20),
    'product_preview_limit' => (int) env('DROPflow_PRODUCT_PREVIEW_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Live read-only mode (production test phase)
    |--------------------------------------------------------------------------
    | When true: mock disabled, GET/HEAD only to OpenCart, no sync/import.
    */
    'live_read_only' => filter_var(env('DROPflow_LIVE_READ_ONLY', false), FILTER_VALIDATE_BOOLEAN),

    'oc_mock' => filter_var(env('DROPflow_LIVE_READ_ONLY', false), FILTER_VALIDATE_BOOLEAN)
        ? false
        : filter_var(env('DROPflow_OC_MOCK', false), FILTER_VALIDATE_BOOLEAN),

    'allow_opencart_sync' => filter_var(env('DROPflow_LIVE_READ_ONLY', false), FILTER_VALIDATE_BOOLEAN)
        ? false
        : filter_var(env('DROPflow_ALLOW_OPENCART_SYNC', false), FILTER_VALIDATE_BOOLEAN),

    'connection_test_limit' => 1,
    'connection_test_timeout' => (int) env('DROPflow_CONNECTION_TEST_TIMEOUT', 8),
    'connection_ping_timeout' => (int) env('DROPflow_CONNECTION_PING_TIMEOUT', 5),

    'ibs_endpoints' => [
        'connection_test' => 'index.php?route=api/ibs/connection_test',
        'products' => 'index.php?route=api/ibs/products',
        'orders' => 'index.php?route=api/ibs/orders',
        'order_statuses' => 'index.php?route=api/ibs/order_queue_statuses',
    ],

    'modules' => [
        'connection' => true,
        'product_map' => env('DROPflow_MODULE_PRODUCT_MAP', false),
        'order_map' => env('DROPflow_MODULE_ORDER_MAP', false),
    ],

    'roadmap' => [
        1 => ['key' => 'connection', 'label' => 'Connection', 'status' => 'active'],
        2 => ['key' => 'product_map', 'label' => 'Product Map', 'status' => 'preview'],
        3 => ['key' => 'order_map', 'label' => 'Order Map', 'status' => 'pending'],
    ],

    'status_ranks' => [
        'ignore' => 0,
        'cancelled' => 1,
        'hold' => 2,
        'new' => 3,
        'accepted' => 4,
        'packed' => 5,
        'dispatched' => 6,
        'returned' => 7,
        'delivered' => 8,
    ],

    'supplier_transitions' => [
        'new' => ['accepted', 'cancelled'],
        'accepted' => ['packed', 'cancelled'],
        'packed' => ['dispatched', 'cancelled'],
        'dispatched' => [],
    ],

    'oc_override_statuses' => ['hold', 'cancelled'],

    'default_new_oc_statuses' => [
        'new', 'pending', 'processing', 'awaiting payment', 'awaiting fulfillment',
    ],
];
