<?php

namespace ibs;

/**
 * Detect POIP / option-image table columns and build read-only joins to product_option_value.
 */
class option_image_schema
{
    private const IMAGE_COLUMNS = ['image', 'optionimage', 'option_image', 'thumb', 'path', 'filename'];

    private const TABLE_PRIORITY = [
        'poip_option_image',
        'poip_product_option_value',
        'product_option_value_image',
        'improved_option_value',
    ];

    /** @var mixed */
    private $db;

    /** @var array<string, mixed> */
    private $cache = [];

    public function __construct($registry)
    {
        $this->db = $registry->get('db');
    }

    /**
     * @return array{select: string, join: string, sources: array<int, array<string, mixed>>}
     */
    public function resolveJoin(string $povAlias): array
    {
        $selectParts = [];
        $joins = '';
        $sources = [];
        $joinIndex = 0;

        foreach (self::IMAGE_COLUMNS as $column) {
            if ($this->columnExists('product_option_value', $column)) {
                $selectParts[] = 'NULLIF(' . $povAlias . '.' . $column . ', \'\')';
                $sources[] = [
                    'table' => DB_PREFIX . 'product_option_value',
                    'image_column' => $column,
                    'join_keys' => ['inline_column'],
                ];
                break;
            }
        }

        foreach ($this->extensionTableCandidates() as $tableSuffix) {
            $built = $this->buildExtensionSource($tableSuffix, $povAlias, $joinIndex);
            if ($built === null) {
                continue;
            }

            $joinIndex++;
            $joins .= $built['join'];
            $selectParts[] = $built['select'];
            $sources[] = $built['source'];
        }

        if ($selectParts === []) {
            return ['select' => '', 'join' => '', 'sources' => []];
        }

        return [
            'select' => ', COALESCE(' . implode(', ', $selectParts) . ') AS option_image',
            'join' => $joins,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<int, int|string> $sampleValueIds
     */
    public function probe(array $sampleValueIds = []): array
    {
        $detected = [];
        foreach ($this->extensionTableCandidates() as $tableSuffix) {
            if (!$this->tableExists($tableSuffix)) {
                continue;
            }

            $columns = $this->listColumns($tableSuffix);
            $imageColumns = array_values(array_intersect($columns, self::IMAGE_COLUMNS));
            $joinKeys = $this->resolveJoinKeys($columns);

            $detected[] = [
                'table' => DB_PREFIX . $tableSuffix,
                'columns' => $columns,
                'image_columns' => $imageColumns,
                'join_keys' => $joinKeys,
                'has_product_option_value_id' => in_array('product_option_value_id', $columns, true),
            ];
        }

        if ($this->tableExists('product_option_value')) {
            $povColumns = $this->listColumns('product_option_value');
            $detected = array_merge([[
                'table' => DB_PREFIX . 'product_option_value',
                'columns' => $povColumns,
                'image_columns' => array_values(array_intersect($povColumns, self::IMAGE_COLUMNS)),
                'join_keys' => ['inline_column'],
                'has_product_option_value_id' => true,
            ]], $detected);
        }

        $ids = [];
        foreach ($sampleValueIds as $valueId) {
            $valueId = (int) $valueId;
            if ($valueId > 0) {
                $ids[$valueId] = $valueId;
            }
        }
        if ($ids === []) {
            $ids = [971, 972, 1011, 1024];
        }

        $imageJoin = $this->resolveJoin('pov');
        $samples = [];
        if (($imageJoin['select'] ?? '') !== '') {
            $idList = implode(',', array_map('intval', array_values($ids)));
            $sql = 'SELECT pov.product_option_value_id' . $imageJoin['select']
                . ' FROM `' . DB_PREFIX . 'product_option_value` pov '
                . $imageJoin['join']
                . ' WHERE pov.product_option_value_id IN (' . $idList . ')';
            $query = $this->db->query($sql);
            foreach ($query->rows as $row) {
                $valueId = (int) ($row['product_option_value_id'] ?? 0);
                if ($valueId > 0) {
                    $samples[(string) $valueId] = trim((string) ($row['option_image'] ?? ''));
                }
            }
        }

        $nonEmptySamples = array_filter($samples, static function ($path) {
            return trim((string) $path) !== '';
        });

        return [
            'join_active' => ($imageJoin['select'] ?? '') !== '',
            'resolved_sources' => $imageJoin['sources'] ?? [],
            'detected_tables' => $detected,
            'sample_value_ids' => array_values($ids),
            'sample_images' => $samples,
            'sample_images_non_empty' => count($nonEmptySamples),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function poipSchemaReport(): array
    {
        $report = [];
        foreach ($this->listPoipTableSuffixes() as $tableSuffix) {
            $columns = $this->listColumns($tableSuffix);
            $report[DB_PREFIX . $tableSuffix] = [
                'columns' => $columns,
                'image_columns' => array_values(array_intersect($columns, self::IMAGE_COLUMNS)),
                'join_keys' => $this->resolveJoinKeys($columns),
            ];
        }

        return $report;
    }

    /**
     * @return array<int, string>
     */
    private function extensionTableCandidates(): array
    {
        $candidates = self::TABLE_PRIORITY;
        foreach ($this->listPoipTableSuffixes() as $suffix) {
            if (!in_array($suffix, $candidates, true)) {
                $candidates[] = $suffix;
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function listPoipTableSuffixes(): array
    {
        $key = 'poip_tables';
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $prefix = DB_PREFIX;
        $suffixes = [];
        $query = $this->db->query('SHOW TABLES');
        foreach ($query->rows as $row) {
            $name = (string) reset($row);
            if ($name === '' || strpos($name, $prefix) !== 0) {
                continue;
            }

            $suffix = substr($name, strlen($prefix));
            $lower = strtolower($suffix);
            if (strpos($lower, 'poip') !== false || strpos($lower, 'option_value_image') !== false) {
                $suffixes[] = $suffix;
            }
        }

        sort($suffixes);
        $this->cache[$key] = $suffixes;

        return $suffixes;
    }

    /**
     * @return array{join: string, select: string, source: array<string, mixed>}|null
     */
    private function buildExtensionSource(string $tableSuffix, string $povAlias, int $joinIndex): ?array
    {
        if (!$this->tableExists($tableSuffix)) {
            return null;
        }

        $columns = $this->listColumns($tableSuffix);
        $imageColumn = $this->firstMatching($columns, self::IMAGE_COLUMNS);
        if ($imageColumn === null) {
            return null;
        }

        $joinKeys = $this->resolveJoinKeys($columns);
        if ($joinKeys === []) {
            return null;
        }

        $alias = 'oimg' . $joinIndex;
        $fullTable = DB_PREFIX . $tableSuffix;
        $onParts = [];
        foreach ($joinKeys as $joinKey) {
            $onParts[] = $alias . '.' . $joinKey . ' = ' . $povAlias . '.' . $joinKey;
        }

        $groupBy = implode(', ', array_map(function ($key) {
            return '`' . $key . '`';
        }, $joinKeys));

        $join = ' LEFT JOIN ('
            . 'SELECT ' . $groupBy . ', MIN(NULLIF(`' . $imageColumn . '`, \'\')) AS `' . $imageColumn . '` '
            . 'FROM `' . $fullTable . '` '
            . 'GROUP BY ' . $groupBy
            . ') ' . $alias . ' ON ' . implode(' AND ', $onParts);

        return [
            'join' => $join,
            'select' => 'NULLIF(' . $alias . '.' . $imageColumn . ', \'\')',
            'source' => [
                'table' => $fullTable,
                'image_column' => $imageColumn,
                'join_keys' => $joinKeys,
            ],
        ];
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function resolveJoinKeys(array $columns): array
    {
        $keys = [];
        if (in_array('product_option_value_id', $columns, true)) {
            $keys[] = 'product_option_value_id';
        }
        if (in_array('product_id', $columns, true)) {
            $keys[] = 'product_id';
        }
        if ($keys === [] && in_array('product_option_id', $columns, true) && in_array('option_value_id', $columns, true)) {
            $keys[] = 'product_option_id';
            $keys[] = 'option_value_id';
            if (in_array('product_id', $columns, true)) {
                $keys[] = 'product_id';
            }
        } elseif ($keys === [] && in_array('option_value_id', $columns, true)) {
            $keys[] = 'option_value_id';
            if (in_array('product_id', $columns, true)) {
                $keys[] = 'product_id';
            }
        } elseif ($keys === [] && in_array('product_option_id', $columns, true)) {
            $keys[] = 'product_option_id';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $candidates
     */
    private function firstMatching(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function listColumns(string $tableSuffix): array
    {
        $key = 'cols:' . $tableSuffix;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if (!$this->tableExists($tableSuffix)) {
            $this->cache[$key] = [];

            return [];
        }

        $query = $this->db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $tableSuffix . '`');
        $columns = [];
        foreach ($query->rows as $row) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $this->cache[$key] = $columns;

        return $columns;
    }

    private function tableExists(string $tableSuffix): bool
    {
        $key = 't:' . $tableSuffix;
        if (isset($this->cache[$key])) {
            return (bool) $this->cache[$key];
        }

        $query = $this->db->query(
            'SHOW TABLES LIKE \'' . $this->db->escape(DB_PREFIX . $tableSuffix) . '\''
        );
        $this->cache[$key] = $query->num_rows > 0;

        return $this->cache[$key];
    }

    private function columnExists(string $tableSuffix, string $column): bool
    {
        return in_array($column, $this->listColumns($tableSuffix), true);
    }
}
