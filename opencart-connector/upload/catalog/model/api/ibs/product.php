<?php

/**
 * Read-only product + variant queries (Dispatch Location bridge, POIP option images).
 */
class ModelApiIbsProduct extends Model
{
    private $bridgeAvailable = null;
    private $optionImageJoin = null;
    private $optionModelColumn = null;
    private $tableExistsCache = [];

    /** @var \ibs\option_image_schema|null */
    private $optionImageSchema = null;

    public function bridgeAvailable(string $bridgeTable): bool
    {
        if ($this->bridgeAvailable !== null) {
            return $this->bridgeAvailable;
        }

        $this->bridgeAvailable = $this->tableExists($bridgeTable)
            && $this->columnExists($bridgeTable, 'product_id')
            && $this->columnExists($bridgeTable, 'from_warehouse');

        return $this->bridgeAvailable;
    }

    public function getPagedProducts(string $bridgeTable, int $page, int $limit): array
    {
        if (!$this->bridgeAvailable($bridgeTable)) {
            return ['products' => [], 'total' => 0];
        }

        $languageId = (int) $this->config->get('config_language_id');
        $offset = ($page - 1) * $limit;
        $bridge = DB_PREFIX . $bridgeTable;

        $totalQuery = $this->db->query(
            'SELECT COUNT(DISTINCT p.product_id) AS total '
            . 'FROM `' . DB_PREFIX . 'product` p '
            . 'INNER JOIN `' . $bridge . '` dlp ON dlp.product_id = p.product_id AND dlp.from_warehouse = 1'
        );
        $total = (int) ($totalQuery->row['total'] ?? 0);

        $productQuery = $this->db->query(
            'SELECT DISTINCT p.product_id, pd.name, p.model, p.image, p.price, p.quantity, p.status, dlp.from_warehouse '
            . 'FROM `' . DB_PREFIX . 'product` p '
            . 'INNER JOIN `' . DB_PREFIX . 'product_description` pd ON p.product_id = pd.product_id AND pd.language_id = ' . $languageId . ' '
            . 'INNER JOIN `' . $bridge . '` dlp ON dlp.product_id = p.product_id AND dlp.from_warehouse = 1 '
            . 'ORDER BY p.product_id ASC '
            . 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );

        $products = [];
        $productIds = [];

        foreach ($productQuery->rows as $row) {
            $productId = (int) $row['product_id'];
            $productIds[] = $productId;
            $products[$productId] = [
                'product_id' => (string) $productId,
                'name' => (string) ($row['name'] ?? ''),
                'model' => (string) ($row['model'] ?? ''),
                'image' => (string) ($row['image'] ?? ''),
                'price' => round((float) ($row['price'] ?? 0), 2),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'status' => (int) ($row['status'] ?? 0),
                'from_warehouse' => (int) ($row['from_warehouse'] ?? 1),
                'options' => [],
            ];
        }

        if ($productIds !== []) {
            $optionsByProduct = $this->getOptionsForProducts($productIds, $languageId);
            foreach ($optionsByProduct as $productId => $options) {
                if (isset($products[$productId])) {
                    $products[$productId]['options'] = $options;
                }
            }
        }

        return [
            'products' => array_values($products),
            'total' => $total,
        ];
    }

    /**
     * @param array<int, int|string> $sampleValueIds
     */
    public function probeOptionImageSources(array $sampleValueIds = []): array
    {
        return $this->optionImageSchema()->probe($sampleValueIds);
    }

    private function getOptionsForProducts(array $productIds, int $languageId): array
    {
        $ids = array_values(array_filter(array_map('intval', $productIds), function ($id) {
            return (int) $id > 0;
        }));
        if ($ids === []) {
            return [];
        }

        $idList = implode(',', $ids);
        $modelSelect = $this->resolveOptionModelSelect('pov');
        $imageJoin = $this->resolveOptionImageJoin('pov');

        $sql = 'SELECT po.product_id, po.product_option_id, pov.product_option_value_id, po.required, '
            . 'od.name AS option_name, ovd.name AS option_value, pov.quantity, pov.subtract, pov.price, pov.price_prefix'
            . $modelSelect . $imageJoin['select']
            . ' FROM `' . DB_PREFIX . 'product_option` po '
            . 'INNER JOIN `' . DB_PREFIX . 'product_option_value` pov ON po.product_option_id = pov.product_option_id '
            . 'INNER JOIN `' . DB_PREFIX . 'option_description` od ON po.option_id = od.option_id AND od.language_id = ' . $languageId . ' '
            . 'INNER JOIN `' . DB_PREFIX . 'option_value_description` ovd ON pov.option_value_id = ovd.option_value_id AND ovd.language_id = ' . $languageId . ' '
            . $imageJoin['join']
            . ' WHERE po.product_id IN (' . $idList . ') '
            . 'ORDER BY po.product_id ASC, po.product_option_id ASC, pov.product_option_value_id ASC';

        $query = $this->db->query($sql);
        $grouped = [];

        foreach ($query->rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $option = [
                'product_option_id' => (string) ($row['product_option_id'] ?? ''),
                'product_option_value_id' => (string) ($row['product_option_value_id'] ?? ''),
                'option_name' => (string) ($row['option_name'] ?? ''),
                'option_value' => (string) ($row['option_value'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'subtract' => (int) ($row['subtract'] ?? 0),
                'price' => round((float) ($row['price'] ?? 0), 2),
                'price_prefix' => (string) ($row['price_prefix'] ?? '+'),
                'required' => (int) ($row['required'] ?? 0),
            ];

            if (array_key_exists('option_model', $row)) {
                $option['model'] = (string) ($row['option_model'] ?? '');
            }

            if (array_key_exists('option_image', $row)) {
                $option['image'] = (string) ($row['option_image'] ?? '');
            }

            $grouped[$productId][] = $option;
        }

        return $grouped;
    }

    private function resolveOptionModelSelect(string $alias): string
    {
        if ($this->optionModelColumn === null) {
            $this->optionModelColumn = $this->columnExists('product_option_value', 'model') ? 'model' : '';
        }

        if ($this->optionModelColumn === '') {
            return '';
        }

        return ', ' . $alias . '.' . $this->optionModelColumn . ' AS option_model';
    }

    private function resolveOptionImageJoin(string $alias): array
    {
        if ($this->optionImageJoin !== null) {
            return $this->optionImageJoin;
        }

        $resolved = $this->optionImageSchema()->resolveJoin($alias);
        $this->optionImageJoin = [
            'select' => $resolved['select'] ?? '',
            'join' => $resolved['join'] ?? '',
        ];

        return $this->optionImageJoin;
    }

    private function optionImageSchema(): \ibs\option_image_schema
    {
        if ($this->optionImageSchema === null) {
            require_once DIR_SYSTEM . 'library/ibs/option_image_schema.php';
            $this->optionImageSchema = new \ibs\option_image_schema($this->registry);
        }

        return $this->optionImageSchema;
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
