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
        3 => ['key' => 'order_map', 'label' => 'Order Map', 'status' => 'active'],
    ],

    'status_ranks' => [
        'ignore' => 0,
        'rejected' => 1,
        'new' => 2,
        'accepted' => 3,
        'packed' => 4,
        'dispatched' => 5,
        'return_queue' => 6,
        'return_received' => 7,
        'completed' => 8,
    ],

    'supplier_transitions' => [
        'new' => ['accepted', 'rejected'],
        'accepted' => ['packed', 'rejected'],
        'packed' => ['dispatched', 'rejected'],
        'dispatched' => ['return_queue'],
        'return_queue' => ['return_received'],
        'return_received' => [],
        'rejected' => [],
        'completed' => [],
    ],

    'oc_override_statuses' => [],

    'default_new_oc_statuses' => [
        'new', 'pending', 'processing', 'awaiting payment', 'awaiting fulfillment',
    ],

    'order_stock_reason' => 'Correction',
];
