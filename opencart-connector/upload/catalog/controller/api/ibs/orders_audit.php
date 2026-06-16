<?php

/**
 * IBS Sync Connector — order filter audit (status-only verification).
 * Route: index.php?route=api/ibs/orders_audit&api_token=...&status_ids[]=25&page=1&limit=20
 */
class ControllerApiIbsOrdersAudit extends Controller
{
    public function index()
    {
        require_once DIR_SYSTEM . 'library/ibs/orders_audit_runner.php';

        $payload = ibs_orders_audit_build_payload($this->registry);
        if (isset($payload['route'])) {
            $payload['route'] = 'api/ibs/orders_audit';
        }

        ibs_orders_audit_send_json($this->registry, $payload);
    }
}
