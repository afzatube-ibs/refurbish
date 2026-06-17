<?php
class ModelExtensionModuleLokkisonaCustomInvoicePro extends Model {
    public function getOrder($order_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
        return $query->row;
    }

    public function getOrderProducts($order_id) {
        $products = array();
        $product_query = $this->db->query("SELECT op.*, p.image AS product_image FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) WHERE op.order_id = '" . (int)$order_id . "' ORDER BY op.order_product_id ASC");

        foreach ($product_query->rows as $product) {
            $option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$product['order_product_id'] . "' ORDER BY order_option_id ASC");
            $options = array();

            foreach ($option_query->rows as $option) {
                $option_value_id = !empty($option['product_option_value_id']) ? (int)$option['product_option_value_id'] : 0;
                $options[] = array(
                    'name'  => isset($option['name']) ? $option['name'] : '',
                    'value' => isset($option['value']) ? $option['value'] : '',
                    'type'  => isset($option['type']) ? $option['type'] : '',
                    'image' => $this->getOptionImage((int)$product['product_id'], $option_value_id)
                );
            }

            $product['options'] = $options;
            $products[] = $product;
        }

        return $products;
    }

    public function getOrderTotals($order_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");
        return $query->rows;
    }

    public function getSteadfastInfo($order_id) {
        $info = array(
            'parcel_id'      => '',
            'consignment_id' => '',
            'status'         => '',
            'tracking_code'  => '',
            'tracking_url'   => '',
            'store_name'     => $this->getMerchantStoreName(),
            'qr_code'        => ''
        );

        $rows = $this->getPossibleSteadfastRows($order_id);

        foreach ($rows as $row) {
            $this->mergeSteadfastRow($info, $row);
        }

        if (!$this->hasSteadfastInfo($info)) {
            foreach ($this->getOrderHistoryComments($order_id) as $comment) {
                $this->mergeSteadfastText($info, $comment);
                if ($this->hasSteadfastInfo($info)) {
                    break;
                }
            }
        }

        if (!$info['tracking_code']) {
            $info['tracking_code'] = $info['consignment_id'] ?: $info['parcel_id'];
        }

        if (!$info['parcel_id'] && $info['consignment_id']) {
            $info['parcel_id'] = $info['consignment_id'];
        }

        if (!$info['tracking_url'] && $info['tracking_code']) {
            $info['tracking_url'] = 'https://steadfast.com.bd/t/' . rawurlencode($info['tracking_code']);
        }

        return $info;
    }

    private function getPossibleSteadfastRows($order_id) {
        $rows = array();

        $rows = array_merge($rows, $this->getOrderManagerPro2CourierRows($order_id));
        $rows = array_merge($rows, $this->getStandaloneSteadfastRows($order_id));

        $preferred_tables = array(
            DB_PREFIX . 'steadfast_order',
            DB_PREFIX . 'steadfast_orders',
            DB_PREFIX . 'steadfast_courier_order',
            DB_PREFIX . 'order_manager_pro2_courier_order',
            DB_PREFIX . 'order_manager_tracking',
            DB_PREFIX . 'steadfast_courier',
            DB_PREFIX . 'steadfast_consignment',
            DB_PREFIX . 'steadfast_parcel',
            DB_PREFIX . 'courier_order',
            DB_PREFIX . 'order_courier',
            DB_PREFIX . 'order_steadfast',
            DB_PREFIX . 'order_parcel',
            DB_PREFIX . 'order_shipment',
            DB_PREFIX . 'order_manager_tracking'
        );

        foreach ($preferred_tables as $table) {
            $rows = array_merge($rows, $this->getSteadfastRowsFromTable($table, $order_id));
        }

        foreach ($this->findCourierTables() as $table) {
            $rows = array_merge($rows, $this->getSteadfastRowsFromTable($table, $order_id));
        }

        return $rows;
    }


    private function getOrderManagerPro2CourierRows($order_id) {
        $rows = array();
        $table = DB_PREFIX . 'order_manager_pro2_courier_order';
        if ($this->tableExists($table)) {
            $query = $this->db->query("SELECT * FROM `" . $this->db->escape($table) . "` WHERE `order_id` = '" . (int)$order_id . "' AND (`courier_code` = 'steadfast' OR `courier_code` = '' OR `courier_code` IS NULL) ORDER BY courier_order_id DESC LIMIT 3");
            foreach ($query->rows as $row) {
                if (isset($row['external_id']) && !isset($row['consignment_id'])) {
                    $row['consignment_id'] = $row['external_id'];
                }
                if (!empty($row['tracking_code']) && empty($row['tracking_url'])) {
                    $row['tracking_url'] = 'https://steadfast.com.bd/t/' . rawurlencode($row['tracking_code']);
                }
                if (empty($row['store_name'])) { $row['store_name'] = $this->getMerchantStoreName(); }
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function getStandaloneSteadfastRows($order_id) {
        $rows = array();
        $table = DB_PREFIX . 'steadfast_courier_order';
        if ($this->tableExists($table)) {
            $query = $this->db->query("SELECT * FROM `" . $this->db->escape($table) . "` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY order_id DESC LIMIT 1");
            foreach ($query->rows as $row) {
                if (!empty($row['tracking_code']) && empty($row['tracking_url'])) {
                    $row['tracking_url'] = 'https://steadfast.com.bd/t/' . rawurlencode($row['tracking_code']);
                }
                if (empty($row['store_name'])) { $row['store_name'] = $this->getMerchantStoreName(); }
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function getSteadfastRowsFromTable($table, $order_id) {
        $rows = array();

        if (!$this->tableExists($table)) {
            return $rows;
        }

        $columns = $this->getColumns($table);
        $order_column = $this->firstColumn($columns, array('order_id', 'oc_order_id', 'reference_order_id'));

        if (!$order_column) {
            return $rows;
        }

        $query = $this->db->query("SELECT * FROM `" . $this->db->escape($table) . "` WHERE `" . $this->db->escape($order_column) . "` = '" . (int)$order_id . "' ORDER BY 1 DESC LIMIT 3");

        foreach ($query->rows as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function findCourierTables() {
        $tables = array();
        $query = $this->db->query("SHOW TABLES");

        foreach ($query->rows as $row) {
            $table = reset($row);
            $name = strtolower($table);
            if (strpos($name, 'steadfast') !== false || strpos($name, 'courier') !== false || strpos($name, 'consign') !== false || strpos($name, 'parcel') !== false || strpos($name, 'shipment') !== false) {
                $tables[] = $table;
            }
        }

        return array_values(array_unique($tables));
    }

    private function mergeSteadfastRow(&$info, $row) {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $this->mergeSteadfastRow($info, $decoded);
                }
            }
        }

        $info['parcel_id']      = $info['parcel_id'] ?: $this->firstExisting($row, array('parcel_id', 'parcel', 'parcel_no', 'parcel_number', 'delivery_id', 'delivery_no', 'shipment_id', 'external_id', 'consignment_id'));
        $info['consignment_id'] = $info['consignment_id'] ?: $this->firstExisting($row, array('consignment_id', 'consignmentId', 'external_id', 'consignment_no', 'consignment_number', 'cn_id', 'courier_consignment_id', 'tracking_consignment_id'));
        $info['status']         = $info['status'] ?: $this->statusLabel($this->firstExisting($row, array('courier_status', 'status', 'delivery_status', 'shipment_status', 'parcel_status', 'current_status')));
        $info['tracking_code']  = $info['tracking_code'] ?: $this->firstExisting($row, array('tracking_code', 'tracking_number', 'tracking_no', 'tracking_id', 'tracking', 'awb', 'awb_number'));
        $info['tracking_url']   = $info['tracking_url'] ?: $this->firstExisting($row, array('tracking_url', 'track_url', 'url', 'tracking_link', 'consignment_url'));
        $row_store_name = $this->firstExisting($row, array('store_name', 'merchant_store_name', 'merchant_name', 'business_name', 'company_name', 'pickup_store', 'pickup_store_name', 'shop_name', 'sender_name'));
        if ($row_store_name && strtolower($row_store_name) !== 'steadfast') {
            $info['store_name'] = $row_store_name;
        }
        $info['qr_code']        = $info['qr_code'] ?: $this->firstExisting($row, array('qr_code', 'qr', 'qr_url', 'qr_image', 'barcode'));

        foreach ($row as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $key_l = strtolower((string)$key);
            $value = trim((string)$value);

            if ($value === '') {
                continue;
            }

            if (!$info['consignment_id'] && (strpos($key_l, 'consign') !== false || $key_l === 'external_id')) { $info['consignment_id'] = $value; }
            if (!$info['parcel_id'] && strpos($key_l, 'parcel') !== false) { $info['parcel_id'] = $value; }
            if (!$info['tracking_code'] && strpos($key_l, 'track') !== false && stripos($value, 'http') !== 0) { $info['tracking_code'] = $value; }
            if (!$info['tracking_url'] && (strpos($key_l, 'track') !== false || strpos($key_l, 'url') !== false) && stripos($value, 'http') === 0) { $info['tracking_url'] = $value; }
            if (!$info['status'] && strpos($key_l, 'status') !== false) { $info['status'] = $value; }
        }
    }

    private function getOrderHistoryComments($order_id) {
        $comments = array();

        if ($this->tableExists(DB_PREFIX . 'order_history')) {
            $query = $this->db->query("SELECT comment FROM `" . DB_PREFIX . "order_history` WHERE order_id = '" . (int)$order_id . "' AND comment <> '' ORDER BY order_history_id DESC LIMIT 20");
            foreach ($query->rows as $row) {
                $comments[] = html_entity_decode(strip_tags($row['comment']), ENT_QUOTES, 'UTF-8');
            }
        }

        return $comments;
    }

    private function mergeSteadfastText(&$info, $text) {
        if (!is_string($text) || $text === '') {
            return;
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $this->mergeSteadfastRow($info, $decoded);
        }

        $patterns = array(
            'consignment_id' => '/(?:consignment\s*(?:id|no|number)?|cn\s*id)\s*[:#-]?\s*([A-Za-z0-9_-]+)/i',
            'parcel_id' => '/(?:parcel\s*(?:id|no|number)?|delivery\s*id)\s*[:#-]?\s*([A-Za-z0-9_-]+)/i',
            'tracking_code' => '/(?:tracking\s*(?:code|no|number|id)?)\s*[:#-]?\s*([A-Za-z0-9_-]+)/i',
            'status' => '/(?:courier\s*status|delivery\s*status|status)\s*[:#-]?\s*([A-Za-z0-9 _-]+)/i',
            'tracking_url' => '/https?:\/\/[^\s<>"]+/i'
        );

        foreach ($patterns as $field => $pattern) {
            if (!$info[$field] && preg_match($pattern, $text, $match)) {
                $info[$field] = trim($match[1]);
            }
        }
    }


    private function statusLabel($status) {
        $status = trim((string)$status);
        if ($status === '') { return ''; }
        return ucwords(str_replace('_', ' ', $status));
    }

    private function hasSteadfastInfo($info) {
        return !empty($info['parcel_id']) || !empty($info['consignment_id']) || !empty($info['tracking_code']) || !empty($info['tracking_url']) || !empty($info['status']);
    }

    private function firstColumn($columns, $keys) {
        foreach ($keys as $key) {
            if (in_array($key, $columns)) {
                return $key;
            }
        }
        return '';
    }

    private function getOptionImage($product_id, $product_option_value_id) {
        if (!$product_option_value_id) {
            return '';
        }

        $poip_table = DB_PREFIX . 'poip_option_image';
        if ($this->tableExists($poip_table)) {
            $columns = $this->getColumns($poip_table);
            $image_col = in_array('image', $columns) ? 'image' : (in_array('image_name', $columns) ? 'image_name' : '');
            if ($image_col) {
                $where = array();
                if (in_array('product_id', $columns)) {
                    $where[] = "product_id = '" . (int)$product_id . "'";
                }
                if (in_array('product_option_value_id', $columns)) {
                    $where[] = "product_option_value_id = '" . (int)$product_option_value_id . "'";
                } elseif (in_array('option_value_id', $columns)) {
                    $where[] = "option_value_id = '" . (int)$product_option_value_id . "'";
                }
                if ($where) {
                    $query = $this->db->query("SELECT `" . $this->db->escape($image_col) . "` AS image FROM `" . $this->db->escape($poip_table) . "` WHERE " . implode(' AND ', $where) . " AND `" . $this->db->escape($image_col) . "` <> '' LIMIT 1");
                    if ($query->num_rows && !empty($query->row['image'])) {
                        return $query->row['image'];
                    }
                }
            }
        }

        if ($this->tableExists(DB_PREFIX . 'product_option_value') && in_array('image', $this->getColumns(DB_PREFIX . 'product_option_value'))) {
            $query = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_value_id = '" . (int)$product_option_value_id . "' AND image <> '' LIMIT 1");
            if ($query->num_rows) {
                return $query->row['image'];
            }
        }

        return '';
    }


    private function getMerchantStoreName() {
        $candidates = array(
            $this->config->get('module_steadfast_courier_store_name'),
            $this->config->get('module_steadfast_courier_merchant_name'),
            $this->config->get('module_steadfast_courier_shop_name'),
            $this->config->get('config_name')
        );
        foreach ($candidates as $name) {
            $name = trim((string)$name);
            if ($name !== '' && strtolower($name) !== 'steadfast') {
                return $name;
            }
        }
        return '';
    }

    private function tableExists($table) {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
        return (bool)$query->num_rows;
    }

    private function getColumns($table) {
        $columns = array();
        $query = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table) . "`");
        foreach ($query->rows as $row) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }

    private function firstExisting($row, $keys) {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return '';
    }
}
