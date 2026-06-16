<?php

require_once DIR_SYSTEM . 'library/ibs/api_settings.php';

/**
 * Read-only order queries for IBS Sync Connector (queue status filter only).
 *
 * HARD RULE: Orders are included/excluded ONLY by oc_order.order_status_id
 * (plus optional date_from/date_to request filters). Never filter by product,
 * warehouse bridge, stock, or product map.
 */
class ModelApiIbsOrder extends Model
{
    private const LOG_PREFIX = 'IBS_ORDER_FILTER_SKIP';

    private $orderColumns = null;
    private $settings = null;
    private $tableExistsCache = [];

    /**
     * @param  list<int>  $statusIds
     */
    public function getPagedOrders(int $page, int $limit, array $statusIds, array $filters = []): array
    {
        $statusIds = $this->normalizeStatusIds($statusIds);
        $excludedOrderIds = [];

        if ($statusIds === []) {
            return [
                'orders' => [],
                'total' => 0,
                'filter_applied' => 'queue_status_only',
                'requested_status_ids' => [],
                'matched_order_count' => 0,
                'excluded_order_ids' => [],
                'warning' => 'No order status IDs supplied. Pass status_ids or configure queue statuses in connector admin.',
            ];
        }

        $whereSql = $this->buildWhereSql($statusIds, $filters);
        $total = $this->countOrdersForWhere($whereSql);
        $languageId = (int) $this->config->get('config_language_id');
        $offset = ($page - 1) * $limit;

        $extraSelect = $this->buildExtraSelect('o');
        $query = $this->db->query(
            'SELECT o.order_id, o.invoice_no, o.firstname, o.lastname, o.telephone, o.email, '
            . 'o.order_status_id, o.total, o.date_added, o.shipping_address_1, o.shipping_address_2, '
            . 'o.shipping_city, o.payment_method, o.shipping_method, os.name AS order_status'
            . $extraSelect
            . ' FROM `' . DB_PREFIX . 'order` o '
            . 'LEFT JOIN `' . DB_PREFIX . 'order_status` os ON o.order_status_id = os.order_status_id AND os.language_id = ' . $languageId . ' '
            . 'WHERE ' . $whereSql . ' '
            . 'ORDER BY o.order_id DESC '
            . 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );

        $orders = [];
        $orderIds = [];
        $seenOrderIds = [];

        foreach ($query->rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                $this->recordExclusion($excludedOrderIds, 0, 'invalid_order_id_in_row');
                continue;
            }

            if (isset($seenOrderIds[$orderId])) {
                $this->recordExclusion($excludedOrderIds, $orderId, 'duplicate_order_id_in_result_set');
                continue;
            }

            $seenOrderIds[$orderId] = true;
            $orderIds[] = $orderId;
            $orders[$orderId] = $this->mapOrderRow($row);
        }

        if ($orderIds !== []) {
            $productsByOrder = $this->getProductsForOrders($orderIds);
            foreach ($orderIds as $orderId) {
                if (!isset($orders[$orderId])) {
                    $this->recordExclusion($excludedOrderIds, $orderId, 'missing_after_row_mapping');
                    continue;
                }

                $items = $productsByOrder[$orderId] ?? [];
                $orders[$orderId]['items'] = $items;
                $orders[$orderId]['products'] = $items;
                $orders[$orderId]['total_quantity'] = array_sum(array_map(function (array $item) {
                    return (int) ($item['quantity'] ?? 0);
                }, $items));
            }
        }

        $mappedOrders = array_values($orders);

        return [
            'orders' => $mappedOrders,
            'total' => $total,
            'filter_applied' => 'queue_status_only',
            'requested_status_ids' => $statusIds,
            'matched_order_count' => count($mappedOrders),
            'excluded_order_ids' => $excludedOrderIds,
        ];
    }

    /**
     * Diagnostic audit — compares status-only counts vs filtered pipeline.
     *
     * @param  list<int>  $statusIds
     * @return array<string, mixed>
     */
    public function auditOrderFilter(int $page, int $limit, array $statusIds, array $filters = []): array
    {
        $statusIds = $this->normalizeStatusIds($statusIds);
        $statusOnlyWhere = $this->buildWhereSql($statusIds, []);
        $filteredWhere = $this->buildWhereSql($statusIds, $filters);

        $totalRawOrders = $this->countOrdersForWhere($statusOnlyWhere);
        $totalAfterFilter = $this->countOrdersForWhere($filteredWhere);
        $rawOrderIds = $this->listOrderIdsForWhere($statusOnlyWhere);
        $filteredOrderIds = $this->listOrderIdsForWhere($filteredWhere);

        $excludedOrderIds = [];
        $rawSet = array_fill_keys($rawOrderIds, true);
        $filteredSet = array_fill_keys($filteredOrderIds, true);

        foreach ($rawOrderIds as $orderId) {
            if (!isset($filteredSet[$orderId])) {
                $reason = $this->resolveDateFilterExclusionReason($orderId, $statusIds, $filters);
                $this->recordExclusion($excludedOrderIds, $orderId, $reason);
            }
        }

        $paged = $this->getPagedOrders($page, $limit, $statusIds, $filters);
        foreach ($paged['excluded_order_ids'] ?? [] as $entry) {
            $excludedOrderIds[] = $entry;
        }

        $returnedIds = [];
        foreach ($paged['orders'] ?? [] as $order) {
            if (!is_array($order)) {
                continue;
            }
            $orderId = (int) ($order['order_id'] ?? 0);
            if ($orderId > 0) {
                $returnedIds[] = $orderId;
            }
        }

        $returnedSet = array_fill_keys($returnedIds, true);
        $offset = ($page - 1) * $limit;
        $pageSlice = array_slice($filteredOrderIds, $offset, $limit);
        $pageSliceSet = array_fill_keys($pageSlice, true);

        foreach ($filteredOrderIds as $orderId) {
            if (!isset($rawSet[$orderId])) {
                $this->recordExclusion($excludedOrderIds, $orderId, 'unexpected_not_in_status_only_set');
            }
        }

        foreach ($pageSlice as $orderId) {
            if (!isset($returnedSet[$orderId])) {
                $this->recordExclusion($excludedOrderIds, $orderId, 'missing_from_paged_response');
            }
        }

        foreach ($returnedIds as $orderId) {
            if (!isset($pageSliceSet[$orderId])) {
                $this->recordExclusion($excludedOrderIds, $orderId, 'returned_outside_requested_page_slice');
            }
        }

        $paginationExcluded = [];
        foreach ($filteredOrderIds as $index => $orderId) {
            if ($index < $offset || $index >= ($offset + $limit)) {
                $paginationExcluded[] = [
                    'order_id' => $orderId,
                    'reason' => 'pagination_outside_page_' . $page,
                ];
            }
        }

        $warehouseDiagnostic = $this->diagnoseWarehouseWouldExclude($statusIds);
        $emptyLinesDiagnostic = $this->diagnoseOrdersWithoutLineItems($statusIds);

        return [
            'filter_applied' => 'queue_status_only',
            'requested_status_ids' => $statusIds,
            'filters_received' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'page' => $page,
            'limit' => $limit,
            'total_raw_orders' => $totalRawOrders,
            'total_after_filter' => $totalAfterFilter,
            'total_returned_this_page' => count($returnedIds),
            'raw_order_ids' => $rawOrderIds,
            'filtered_order_ids' => $filteredOrderIds,
            'returned_order_ids' => $returnedIds,
            'excluded_order_ids' => $this->uniqueExclusions($excludedOrderIds),
            'pagination_excluded_order_ids' => $paginationExcluded,
            'status_breakdown' => $this->countByStatus($statusIds),
            'diagnostics' => [
                'warehouse_bridge_not_applied' => true,
                'would_exclude_if_warehouse_bridge' => $warehouseDiagnostic,
                'orders_without_line_items' => $emptyLinesDiagnostic,
                'product_join_not_used_in_order_query' => true,
            ],
            'audit_note' => 'Orders API uses ONLY order_status_id (+ optional date filters). '
                . 'If total_raw_orders is lower than OpenCart admin, compare admin store filter and confirm status #25 is current order_status_id.',
        ];
    }

    /**
     * @param  list<int>  $statusIds
     * @return list<int>
     */
    private function normalizeStatusIds(array $statusIds): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $statusIds),
            function (int $id) {
                return $id > 0;
            }
        )));
    }

    /**
     * @param  list<int>  $statusIds
     */
    private function buildWhereSql(array $statusIds, array $filters): string
    {
        $statusIds = $this->normalizeStatusIds($statusIds);
        $where = ['o.order_status_id IN (' . implode(',', $statusIds) . ')'];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'DATE(o.date_added) >= \'' . $this->db->escape($dateFrom) . '\'';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'DATE(o.date_added) <= \'' . $this->db->escape($dateTo) . '\'';
        }

        return implode(' AND ', $where);
    }

    private function countOrdersForWhere(string $whereSql): int
    {
        $query = $this->db->query(
            'SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'order` o WHERE ' . $whereSql
        );

        return (int) ($query->row['total'] ?? 0);
    }

    /**
     * @return list<int>
     */
    private function listOrderIdsForWhere(string $whereSql): array
    {
        $query = $this->db->query(
            'SELECT o.order_id FROM `' . DB_PREFIX . 'order` o WHERE ' . $whereSql . ' ORDER BY o.order_id DESC'
        );

        $ids = [];
        foreach ($query->rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId > 0) {
                $ids[] = $orderId;
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $statusIds
     * @return array<string, mixed>
     */
    private function countByStatus(array $statusIds): array
    {
        $breakdown = [];
        foreach ($this->normalizeStatusIds($statusIds) as $statusId) {
            $query = $this->db->query(
                'SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'order` o WHERE o.order_status_id = ' . (int) $statusId
            );
            $breakdown[(string) $statusId] = (int) ($query->row['total'] ?? 0);
        }

        return $breakdown;
    }

    /**
     * Diagnostic ONLY — shows orders that an incorrect warehouse join would drop.
     *
     * @param  list<int>  $statusIds
     * @return array<string, mixed>
     */
    private function diagnoseWarehouseWouldExclude(array $statusIds): array
    {
        $statusIds = $this->normalizeStatusIds($statusIds);
        if ($statusIds === []) {
            return [
                'diagnostic_only' => true,
                'bridge_table' => null,
                'count' => 0,
                'order_ids' => [],
            ];
        }

        $bridgeTable = trim((string) ($this->settings()['bridge_table'] ?? 'dispatch_location_product'));
        if ($bridgeTable === ''
            || !$this->tableExists($bridgeTable)
            || !$this->columnExists($bridgeTable, 'product_id')
            || !$this->columnExists($bridgeTable, 'from_warehouse')) {
            return [
                'diagnostic_only' => true,
                'bridge_table' => $bridgeTable !== '' ? DB_PREFIX . $bridgeTable : null,
                'count' => 0,
                'order_ids' => [],
                'note' => 'Warehouse bridge unavailable — cannot simulate wrongful exclusion.',
            ];
        }

        $bridge = DB_PREFIX . $bridgeTable;
        $statusIdList = implode(',', $statusIds);
        $query = $this->db->query(
            'SELECT o.order_id FROM `' . DB_PREFIX . 'order` o '
            . 'WHERE o.order_status_id IN (' . $statusIdList . ') '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM `' . DB_PREFIX . 'order_product` op '
            . 'INNER JOIN `' . $bridge . '` dlp ON dlp.product_id = op.product_id AND dlp.from_warehouse = 1 '
            . 'WHERE op.order_id = o.order_id'
            . ') '
            . 'ORDER BY o.order_id DESC'
        );

        $orderIds = [];
        foreach ($query->rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }

        return [
            'diagnostic_only' => true,
            'bridge_table' => $bridge,
            'count' => count($orderIds),
            'order_ids' => $orderIds,
            'note' => 'NOT applied to orders API. If count > 0 and API total is low, deployed connector may still use warehouse join.',
        ];
    }

    /**
     * @param  list<int>  $statusIds
     * @return array<string, mixed>
     */
    private function diagnoseOrdersWithoutLineItems(array $statusIds): array
    {
        $statusIds = $this->normalizeStatusIds($statusIds);
        if ($statusIds === []) {
            return ['count' => 0, 'order_ids' => []];
        }

        $statusIdList = implode(',', $statusIds);
        $query = $this->db->query(
            'SELECT o.order_id FROM `' . DB_PREFIX . 'order` o '
            . 'WHERE o.order_status_id IN (' . $statusIdList . ') '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM `' . DB_PREFIX . 'order_product` op WHERE op.order_id = o.order_id'
            . ') '
            . 'ORDER BY o.order_id DESC'
        );

        $orderIds = [];
        foreach ($query->rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }

        return [
            'count' => count($orderIds),
            'order_ids' => $orderIds,
            'note' => 'Orders with zero order_product rows are still included by this API.',
        ];
    }

    /**
     * @param  list<int>  $statusIds
     */
    private function resolveDateFilterExclusionReason(int $orderId, array $statusIds, array $filters): string
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom === '' && $dateTo === '') {
            return 'excluded_without_date_filter';
        }

        $query = $this->db->query(
            'SELECT o.order_id, o.date_added, o.order_status_id FROM `' . DB_PREFIX . 'order` o '
            . 'WHERE o.order_id = ' . (int) $orderId . ' LIMIT 1'
        );

        if ($query->num_rows === 0) {
            return 'date_filter_order_not_found';
        }

        $row = $query->row;
        $statusId = (int) ($row['order_status_id'] ?? 0);
        if (!in_array($statusId, $this->normalizeStatusIds($statusIds), true)) {
            return 'order_status_mismatch_id_' . $statusId;
        }

        $dateAdded = substr((string) ($row['date_added'] ?? ''), 0, 10);
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && $dateAdded !== '' && $dateAdded < $dateFrom) {
            return 'date_from_filter';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && $dateAdded !== '' && $dateAdded > $dateTo) {
            return 'date_to_filter';
        }

        return 'date_filter_unknown';
    }

    /**
     * @param  list<array{order_id: int, reason: string}>  $excluded
     */
    private function recordExclusion(array &$excluded, int $orderId, string $reason): void
    {
        $entry = [
            'order_id' => $orderId,
            'reason' => $reason,
        ];
        $excluded[] = $entry;
        $this->logSkippedOrder($orderId, $reason);
    }

    private function logSkippedOrder(int $orderId, string $reason): void
    {
        $message = self::LOG_PREFIX . ' order_id=' . $orderId . ' reason=' . $reason;
        error_log($message);

        if (isset($this->log) && is_object($this->log) && method_exists($this->log, 'write')) {
            $this->log->write($message);
        }
    }

    /**
     * @param  list<array{order_id: int, reason: string}>  $excluded
     * @return list<array{order_id: int, reason: string}>
     */
    private function uniqueExclusions(array $excluded): array
    {
        $seen = [];
        $unique = [];

        foreach ($excluded as $entry) {
            $orderId = (int) ($entry['order_id'] ?? 0);
            $reason = (string) ($entry['reason'] ?? '');
            $key = $orderId . '|' . $reason;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = [
                'order_id' => $orderId,
                'reason' => $reason,
            ];
        }

        return $unique;
    }

    private function mapOrderRow(array $row): array
    {
        $map = $this->settings()['order_field_map'] ?? [];
        $firstName = trim((string) ($row['firstname'] ?? ''));
        $lastName = trim((string) ($row['lastname'] ?? ''));
        $customerName = trim($firstName . ' ' . $lastName);
        $addressParts = array_filter([
            trim((string) ($row['shipping_address_1'] ?? '')),
            trim((string) ($row['shipping_address_2'] ?? '')),
            trim((string) ($row['shipping_city'] ?? '')),
        ]);
        $orderStatusId = (int) ($row['order_status_id'] ?? 0);
        $orderStatusName = (string) ($row['order_status'] ?? '');
        $orderTotal = round((float) ($row['total'] ?? 0), 2);
        $orderId = (string) ($row['order_id'] ?? '');

        return [
            'order_id' => $orderId,
            'source_order_id' => $orderId,
            'invoice_no' => (string) ($row['invoice_no'] ?? ''),
            'firstname' => $firstName,
            'lastname' => $lastName,
            'customer_name' => $customerName,
            'telephone' => (string) ($row['telephone'] ?? ''),
            'customer_phone' => (string) ($row['telephone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'order_status_id' => $orderStatusId,
            'current_oc_status_id' => $orderStatusId,
            'order_status' => $orderStatusName,
            'current_oc_status' => $orderStatusName,
            'order_status_name' => $orderStatusName,
            'total' => $orderTotal,
            'order_total' => $orderTotal,
            'sale_amount' => $orderTotal,
            'date_added' => (string) ($row['date_added'] ?? ''),
            'created_at' => (string) ($row['date_added'] ?? ''),
            'shipping_address_1' => (string) ($row['shipping_address_1'] ?? ''),
            'shipping_address_2' => (string) ($row['shipping_address_2'] ?? ''),
            'shipping_city' => (string) ($row['shipping_city'] ?? ''),
            'customer_address' => implode(', ', $addressParts),
            'payment_method' => (string) ($row['payment_method'] ?? ''),
            'shipping_method' => (string) ($row['shipping_method'] ?? ''),
            'courier_status' => $this->resolveMappedField($row, $map['courier_status'] ?? ['courier_status', 'shipping_status']),
            'consignment_id' => $this->resolveMappedField($row, $map['consignment_id'] ?? ['consignment_id', 'tracking_number', 'tracking_no']),
            'courier_name' => $this->resolveMappedField($row, $map['courier_name'] ?? ['courier_name', 'shipping_method']),
            'items' => [],
            'products' => [],
            'total_quantity' => 0,
        ];
    }

    private function resolveMappedField(array $row, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (array_key_exists($candidate, $row) && (string) $row[$candidate] !== '') {
                return (string) $row[$candidate];
            }
        }

        return '';
    }

    /**
     * Line items only — no product/warehouse/status filters.
     *
     * @param  array<int, int>  $orderIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function getProductsForOrders(array $orderIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $orderIds), function ($id) {
            return (int) $id > 0;
        }));
        if ($ids === []) {
            return [];
        }

        $idList = implode(',', $ids);
        $query = $this->db->query(
            'SELECT op.order_product_id, op.order_id, op.product_id, op.name, op.model, op.quantity, op.price, op.total, op.tax '
            . 'FROM `' . DB_PREFIX . 'order_product` op '
            . 'WHERE op.order_id IN (' . $idList . ') '
            . 'ORDER BY op.order_id ASC, op.order_product_id ASC'
        );

        $optionsByLine = $this->getOrderOptionsForOrders($ids);
        $grouped = [];

        foreach ($query->rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $lineId = (int) ($row['order_product_id'] ?? 0);
            $optionParts = $optionsByLine[$lineId] ?? [
                'option_name' => '',
                'option_value' => '',
                'variant_label' => null,
            ];

            $grouped[$orderId][] = [
                'product_id' => isset($row['product_id']) ? (string) $row['product_id'] : '',
                'source_product_id' => isset($row['product_id']) ? (string) $row['product_id'] : '',
                'name' => (string) ($row['name'] ?? ''),
                'product_name' => (string) ($row['name'] ?? ''),
                'model' => (string) ($row['model'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'qty' => (int) ($row['quantity'] ?? 0),
                'price' => round((float) ($row['price'] ?? 0), 2),
                'sale_price' => round((float) ($row['price'] ?? 0), 2),
                'total' => round((float) ($row['total'] ?? 0), 2),
                'sku' => (string) ($row['model'] ?? ''),
                'option_name' => (string) ($optionParts['option_name'] ?? ''),
                'option_value' => (string) ($optionParts['option_value'] ?? ''),
                'variant_label' => $optionParts['variant_label'] ?? null,
            ];
        }

        return $grouped;
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, array{option_name: string, option_value: string, variant_label: ?string}>
     */
    private function getOrderOptionsForOrders(array $orderIds): array
    {
        $idList = implode(',', array_map('intval', $orderIds));
        $query = $this->db->query(
            'SELECT order_product_id, name, value FROM `' . DB_PREFIX . 'order_option` '
            . 'WHERE order_id IN (' . $idList . ') '
            . 'ORDER BY order_product_id ASC, order_option_id ASC'
        );

        $labels = [];
        foreach ($query->rows as $row) {
            $lineId = (int) ($row['order_product_id'] ?? 0);
            if ($lineId <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if (!isset($labels[$lineId])) {
                $labels[$lineId] = [
                    'option_name' => $name,
                    'option_value' => $value,
                    'variant_label' => null,
                ];
            } else {
                if ($labels[$lineId]['option_name'] !== '' && $name !== '') {
                    $labels[$lineId]['option_name'] .= ', ' . $name;
                } elseif ($name !== '') {
                    $labels[$lineId]['option_name'] = $name;
                }

                if ($labels[$lineId]['option_value'] !== '' && $value !== '') {
                    $labels[$lineId]['option_value'] .= ', ' . $value;
                } elseif ($value !== '') {
                    $labels[$lineId]['option_value'] = $value;
                }
            }

            $primaryName = $labels[$lineId]['option_name'];
            $primaryValue = $labels[$lineId]['option_value'];
            if ($primaryName !== '' && $primaryValue !== '') {
                $labels[$lineId]['variant_label'] = $primaryName . ': ' . $primaryValue;
            } elseif ($primaryValue !== '') {
                $labels[$lineId]['variant_label'] = $primaryValue;
            }
        }

        return $labels;
    }

    private function buildExtraSelect(string $alias): string
    {
        $columns = $this->resolveOrderColumns();
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = ', ' . $alias . '.' . $column;
        }

        return implode('', $parts);
    }

    private function resolveOrderColumns(): array
    {
        if ($this->orderColumns !== null) {
            return $this->orderColumns;
        }

        $map = $this->settings()['order_field_map'] ?? [];
        $candidates = [];
        foreach (['courier_status', 'consignment_id', 'courier_name'] as $key) {
            foreach ((array) ($map[$key] ?? []) as $column) {
                $column = trim((string) $column);
                if ($column !== '') {
                    $candidates[] = $column;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        $existing = [];
        foreach ($candidates as $column) {
            if ($this->columnExists('order', $column)) {
                $existing[] = $column;
            }
        }

        $this->orderColumns = $existing;

        return $this->orderColumns;
    }

    private function columnExists(string $tableSuffix, string $column): bool
    {
        $key = 'c:' . $tableSuffix . ':' . $column;
        if (array_key_exists($key, $this->tableExistsCache)) {
            return $this->tableExistsCache[$key];
        }

        if (!$this->tableExists($tableSuffix)) {
            $this->tableExistsCache[$key] = false;

            return false;
        }

        $query = $this->db->query(
            'SHOW COLUMNS FROM `' . DB_PREFIX . $tableSuffix . '` LIKE \'' . $this->db->escape($column) . '\''
        );
        $this->tableExistsCache[$key] = $query->num_rows > 0;

        return $this->tableExistsCache[$key];
    }

    private function tableExists(string $tableSuffix): bool
    {
        $key = 't:' . $tableSuffix;
        if (array_key_exists($key, $this->tableExistsCache)) {
            return $this->tableExistsCache[$key];
        }

        $query = $this->db->query(
            'SHOW TABLES LIKE \'' . $this->db->escape(DB_PREFIX . $tableSuffix) . '\''
        );
        $this->tableExistsCache[$key] = $query->num_rows > 0;

        return $this->tableExistsCache[$key];
    }

    private function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $loader = new \ibs\api_settings($this->registry);
        $this->settings = $loader->all();

        return $this->settings;
    }
}
