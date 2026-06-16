<?php

/**
 * IBS Sync Connector — supplier order queue statuses (read-only).
 * Route: index.php?route=api/ibs/order_queue_statuses&api_token=...
 */
class ControllerApiIbsOrderQueueStatuses extends Controller
{
    public function index()
    {
        require_once DIR_SYSTEM . 'library/ibs/bootstrap.php';
        list($apiAuth, $apiResponse) = ibs_sync_api_services($this->registry);

        $this->load->model('api/ibs/order_queue_status');

        $authError = $apiAuth->authenticate();
        if ($authError !== null) {
            $apiResponse->error($authError, 401);

            return;
        }

        $settings = $apiAuth->settings();
        $queueStatusIds = $settings['queue_status_ids'] ?? [];
        $statuses = $this->model_api_ibs_order_queue_status->getQueueStatuses($queueStatusIds);
        $selectedCount = 0;
        foreach ($statuses as $status) {
            if (!empty($status['selected'])) {
                $selectedCount++;
            }
        }

        $apiResponse->send([
            'success' => true,
            'read_only' => true,
            'connector_version' => IBS_SYNC_CONNECTOR_VERSION,
            'max_limit' => $apiAuth->maxLimit(),
            'queue_status_ids' => array_values(array_map('strval', $queueStatusIds)),
            'queue_statuses' => $statuses,
            'total_statuses' => count($statuses),
            'selected_count' => $selectedCount,
        ]);
    }
}
