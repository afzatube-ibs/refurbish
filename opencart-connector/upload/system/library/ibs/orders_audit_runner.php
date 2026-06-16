<?php

/**
 * Shared orders audit payload builder for api/ibs/orders_audit and api/ibs/orders/audit.
 *
 * @return array<string, mixed>
 */
function ibs_orders_audit_build_payload($registry): array
{
    require_once DIR_SYSTEM . 'library/ibs/bootstrap.php';
    require_once DIR_SYSTEM . 'library/ibs/connector_version.php';
    if (!defined('IBS_SYNC_CONNECTOR_BUILD')) {
        require_once DIR_SYSTEM . 'library/ibs/connector_build.php';
    }

    list($apiAuth, $apiResponse) = ibs_sync_api_services($registry);

    $authError = $apiAuth->authenticate();
    if ($authError !== null) {
        return [
            '_http_status' => 401,
            'success' => false,
            'error' => $authError,
            'read_only' => true,
        ];
    }

    $registry->get('load')->model('api/ibs/order');
    $model = $registry->get('model_api_ibs_order');

    $settings = $apiAuth->settings();
    $request = $registry->get('request');
    $requestedStatusIds = $apiAuth->requestedStatusIds($settings['queue_status_ids'] ?? []);
    $page = $apiAuth->page();
    $limit = $apiAuth->limit();
    $filters = [
        'date_from' => $request->get['date_from'] ?? null,
        'date_to' => $request->get['date_to'] ?? null,
    ];

    $audit = $model->auditOrderFilter($page, $limit, $requestedStatusIds, $filters);

    return [
        '_http_status' => 200,
        'success' => true,
        'read_only' => true,
        'connector_version' => IBS_SYNC_CONNECTOR_VERSION,
        'connector_build' => defined('IBS_SYNC_CONNECTOR_BUILD') ? IBS_SYNC_CONNECTOR_BUILD : 'legacy',
        'orders_filter_mode' => 'queue_status_only',
        'filter_applied' => 'queue_status_only',
        'route' => 'api/ibs/orders_audit',
        'requested_status_ids' => $audit['requested_status_ids'] ?? $requestedStatusIds,
        'total_raw_orders' => (int) ($audit['total_raw_orders'] ?? 0),
        'total_after_filter' => (int) ($audit['total_after_filter'] ?? 0),
        'total_returned_this_page' => (int) ($audit['total_returned_this_page'] ?? 0),
        'excluded_order_ids' => $audit['excluded_order_ids'] ?? [],
        'pagination_excluded_order_ids' => $audit['pagination_excluded_order_ids'] ?? [],
        'raw_order_ids' => $audit['raw_order_ids'] ?? [],
        'filtered_order_ids' => $audit['filtered_order_ids'] ?? [],
        'returned_order_ids' => $audit['returned_order_ids'] ?? [],
        'status_breakdown' => $audit['status_breakdown'] ?? [],
        'filters_received' => $audit['filters_received'] ?? [],
        'diagnostics' => $audit['diagnostics'] ?? [],
        'audit_note' => $audit['audit_note'] ?? '',
        'page' => $page,
        'limit' => $limit,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function ibs_orders_audit_send_json($registry, array $payload): void
{
    $response = $registry->get('response');
    $request = $registry->get('request');
    $status = (int) ($payload['_http_status'] ?? 200);
    unset($payload['_http_status']);

    $protocol = $request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $response->addHeader($protocol . ' ' . $status);
    $response->addHeader('Content-Type: application/json; charset=utf-8');
    $response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
    $response->setOutput(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
