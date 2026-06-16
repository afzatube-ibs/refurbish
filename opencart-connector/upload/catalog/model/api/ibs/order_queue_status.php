<?php

require_once DIR_SYSTEM . 'library/ibs/api_settings.php';

/**
 * Read-only order queue status list for IBS Sync Connector.
 */
class ModelApiIbsOrderQueueStatus extends Model
{
    public function getQueueStatuses(array $selectedIds): array
    {
        $languageId = (int) $this->config->get('config_language_id');
        $selectedLookup = [];
        foreach ($selectedIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $selectedLookup[$id] = true;
            }
        }

        $query = $this->db->query(
            'SELECT order_status_id, name FROM `' . DB_PREFIX . 'order_status` '
            . 'WHERE language_id = ' . $languageId . ' ORDER BY name ASC'
        );

        $statuses = [];
        foreach ($query->rows as $row) {
            $statusId = (string) ($row['order_status_id'] ?? '');
            if ($statusId === '') {
                continue;
            }

            $statuses[] = [
                'status_id' => $statusId,
                'name' => (string) ($row['name'] ?? ''),
                'selected' => isset($selectedLookup[$statusId]),
            ];
        }

        return $statuses;
    }
}
