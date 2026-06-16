<?php

namespace ibs;

/**
 * Read-only settings loader — OpenCart module settings with legacy file fallback.
 */
class api_settings
{
    private const BRIDGE_TABLE = 'dispatch_location_product';

    private $config;

    public function __construct($registry)
    {
        $this->config = $registry->get('config');
    }

    public function all(): array
    {
        $token = trim((string) $this->config->get('module_ibs_sync_connector_api_token'));
        $status = (int) $this->config->get('module_ibs_sync_connector_status');
        $maxLimit = (int) $this->config->get('module_ibs_sync_connector_max_limit');
        $allowedIps = $this->config->get('module_ibs_sync_connector_allowed_ips');
        $queueStatusIds = $this->queueStatusIds();

        if ($token === '' && !is_file(DIR_SYSTEM . 'config/ibs_api.php')) {
            return $this->legacyDefaults();
        }

        if ($token === '' && is_file(DIR_SYSTEM . 'config/ibs_api.php')) {
            $legacy = $this->legacyFile();
            if ($token === '') {
                $token = trim((string) ($legacy['api_token'] ?? ''));
            }
            if ($maxLimit <= 0) {
                $maxLimit = (int) ($legacy['max_limit'] ?? 20);
            }
            if (!is_array($allowedIps) || $allowedIps === []) {
                $allowedIps = $legacy['allowed_ips'] ?? [];
            }
            $orderFieldMap = $legacy['order_field_map'] ?? $this->defaultOrderFieldMap();
        } else {
            $orderFieldMap = $this->decodeOrderFieldMap(
                (string) $this->config->get('module_ibs_sync_connector_order_field_map')
            );
        }

        return [
            'status' => $status,
            'api_token' => $token,
            'max_limit' => max(1, min($maxLimit > 0 ? $maxLimit : 20, 20)),
            'allowed_ips' => is_array($allowedIps) ? $allowedIps : [],
            'bridge_table' => self::BRIDGE_TABLE,
            'queue_status_ids' => $queueStatusIds,
            'order_field_map' => $orderFieldMap,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function queueStatusIds(): array
    {
        $raw = $this->config->get('module_ibs_sync_connector_queue_status_ids');
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function bridgeTable(): string
    {
        return self::BRIDGE_TABLE;
    }

    public function isEnabled(): bool
    {
        $settings = $this->all();

        return (int) ($settings['status'] ?? 0) === 1 || trim((string) ($settings['api_token'] ?? '')) !== '';
    }

    private function legacyFile(): array
    {
        $settings = require DIR_SYSTEM . 'config/ibs_api.php';

        return is_array($settings) ? $settings : [];
    }

    private function legacyDefaults(): array
    {
        return [
            'status' => 0,
            'api_token' => '',
            'max_limit' => 20,
            'allowed_ips' => [],
            'bridge_table' => self::BRIDGE_TABLE,
            'queue_status_ids' => [],
            'order_field_map' => $this->defaultOrderFieldMap(),
        ];
    }

    private function defaultOrderFieldMap(): array
    {
        return [
            'courier_status' => ['courier_status', 'shipping_status'],
            'consignment_id' => ['consignment_id', 'tracking_number', 'tracking_no'],
            'courier_name' => ['courier_name', 'shipping_method'],
        ];
    }

    private function decodeOrderFieldMap(string $json): array
    {
        if ($json === '') {
            return $this->defaultOrderFieldMap();
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $this->defaultOrderFieldMap();
    }
}
