<?php

/**
 * Compatibility and count probes for IBS Sync Connector (SELECT only).
 */
class ModelApiIbsConnector extends Model
{
    private $tableExistsCache = [];

    public function getCompatibilityReport(): array
    {
        $povImageColumns = [];
        foreach (['optionimage', 'image', 'option_image', 'thumb'] as $column) {
            if ($this->columnExists('product_option_value', $column)) {
                $povImageColumns[] = $column;
            }
        }

        $poipTables = [];
        $improvedTables = [];
        foreach ($this->listTables() as $table) {
            $lower = strtolower($table);
            if (strpos($lower, 'poip') !== false || strpos($lower, 'option_value_image') !== false) {
                $poipTables[] = $table;
            }
            if (strpos($lower, 'improved_option') !== false) {
                $improvedTables[] = $table;
            }
        }

        require_once DIR_SYSTEM . 'library/ibs/option_image_schema.php';
        $schema = new \ibs\option_image_schema($this->registry);

        return [
            'opencart_version' => defined('VERSION') ? (string) VERSION : '',
            'poip_detected' => $poipTables !== [],
            'poip_tables' => $poipTables,
            'poip_schema_probe' => $schema->poipSchemaReport(),
            'improved_options_detected' => $improvedTables !== [],
            'improved_options_tables' => $improvedTables,
            'product_option_value_image_columns' => $povImageColumns,
        ];
    }

    public function getProductCountProbe(string $bridgeTable): array
    {
        if (!$this->tableExists($bridgeTable)
            || !$this->columnExists($bridgeTable, 'product_id')
            || !$this->columnExists($bridgeTable, 'from_warehouse')) {
            return [
                'bridge_available' => false,
                'bridge_table' => DB_PREFIX . $bridgeTable,
                'warehouse_product_count' => 0,
            ];
        }

        $bridge = DB_PREFIX . $bridgeTable;
        $query = $this->db->query(
            'SELECT COUNT(DISTINCT p.product_id) AS total '
            . 'FROM `' . DB_PREFIX . 'product` p '
            . 'INNER JOIN `' . $bridge . '` dlp ON dlp.product_id = p.product_id AND dlp.from_warehouse = 1'
        );

        return [
            'bridge_available' => true,
            'bridge_table' => $bridge,
            'warehouse_product_count' => (int) ($query->row['total'] ?? 0),
        ];
    }

    public function getOrderCountProbe(): array
    {
        $query = $this->db->query('SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'order`');

        return [
            'order_count' => (int) ($query->row['total'] ?? 0),
        ];
    }

    private function listTables(): array
    {
        $query = $this->db->query('SHOW TABLES');
        $prefix = DB_PREFIX;
        $tables = [];
        foreach ($query->rows as $row) {
            $name = (string) reset($row);
            if ($name !== '' && strpos($name, $prefix) === 0) {
                $tables[] = $name;
            }
        }

        return $tables;
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
}
