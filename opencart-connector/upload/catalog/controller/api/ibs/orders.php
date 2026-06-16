<?php

/**
 * IBS Sync Connector — read-only orders filtered by OC order_status_id only.
 * Route: index.php?route=api/ibs/orders&api_token=...&status_ids[]=25&page=1&limit=20
 */
class ControllerApiIbsOrders extends Controller
{
    public function index()
    {
        require_once DIR_SYSTEM . 'library/ibs/bootstrap.php';
        list($apiAuth, $apiResponse) = ibs_sync_api_services($this->registry);

        $this->load->model('api/ibs/order');

        $authError = $apiAuth->authenticate();
        if ($authError !== null) {
            $apiResponse->error($authError, 401);

            return;
        }

        $settings = $apiAuth->settings();
        $requestedStatusIds = $apiAuth->requestedStatusIds($settings['queue_status_ids'] ?? []);
        $page = $apiAuth->page();
        $limit = $apiAuth->limit();
        $filters = [
            'date_from' => $this->request->get['date_from'] ?? null,
            'date_to' => $this->request->get['date_to'] ?? null,
        ];

        $result = $this->model_api_ibs_order->getPagedOrders($page, $limit, $requestedStatusIds, $filters);
        $total = (int) ($result['total'] ?? 0);
        $offset = ($page - 1) * $limit;
        $matchedOrderCount = (int) ($result['matched_order_count'] ?? count($result['orders'] ?? []));

        $payload = [
            'success' => true,
            'read_only' => true,
            'connector_version' => IBS_SYNC_CONNECTOR_VERSION,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_previous' => $page > 1,
            'has_next' => ($offset + $limit) < $total,
            'filter_applied' => 'queue_status_only',
            'requested_status_ids' => $result['requested_status_ids'] ?? $requestedStatusIds,
            'matched_order_count' => $matchedOrderCount,
            'orders' => $result['orders'] ?? [],
        ];

        if (!empty($result['warning'])) {
            $payload['warning'] = (string) $result['warning'];
        }

        $apiResponse->send($payload);
    }

    /**
     * Fallback audit route when orders_audit.php is not deployed:
     * index.php?route=api/ibs/orders/audit&api_token=...
     */
    public function audit()
    {
        require_once DIR_SYSTEM . 'library/ibs/orders_audit_runner.php';

        $payload = ibs_orders_audit_build_payload($this->registry);
        $payload['route'] = 'api/ibs/orders/audit';

        ibs_orders_audit_send_json($this->registry, $payload);
    }
}
