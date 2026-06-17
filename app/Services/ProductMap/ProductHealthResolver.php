<?php

namespace App\Services\ProductMap;

class ProductHealthResolver
{
    public const DEFAULT_LOW_WARNING = 5;

    /** @var array<string, int> */
    protected const HEALTH_PRIORITY = [
        'critical' => 5,
        'warning' => 4,
        'alert' => 3,
        'low' => 3,
        'needs_attention' => 2,
        'ok' => 1,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function applyToProducts(array $products): array
    {
        $duplicateIbsModels = $this->findDuplicateIbsModels($products);

        return array_map(function (array $product) use ($duplicateIbsModels) {
            $lowWarning = (int) ($product['low_warning'] ?? self::DEFAULT_LOW_WARNING);
            $options = is_array($product['options'] ?? null) ? $product['options'] : [];
            $normalizedOptions = array_map(
                fn (array $option) => $this->applyOptionHealth($option, $lowWarning, $duplicateIbsModels),
                $options
            );

            return array_merge($product, [
                'options' => $normalizedOptions,
                'variants' => $normalizedOptions,
                'supplier_cost' => array_key_exists('rate', $product) && $product['rate'] !== null
                    ? (float) $product['rate']
                    : null,
                'health' => $this->assessParentHealth($product, $normalizedOptions, $lowWarning, $duplicateIbsModels),
            ]);
        }, $products);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    public function findDuplicateIbsModels(array $products): array
    {
        $counts = [];

        foreach ($products as $product) {
            $this->tallyIbsModel($counts, (string) ($product['ibs_model'] ?? ''));

            foreach ($product['options'] ?? [] as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $this->tallyIbsModel($counts, (string) ($option['ibs_model'] ?? ''));
            }
        }

        return array_keys(array_filter($counts, fn (int $count) => $count > 1));
    }

    /**
     * @param  array<string, int>  $counts
     */
    protected function tallyIbsModel(array &$counts, string $ibsModel): void
    {
        $ibsModel = trim($ibsModel);

        if ($ibsModel === '') {
            return;
        }

        $counts[$ibsModel] = ($counts[$ibsModel] ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<int, string>  $duplicateIbsModels
     * @return array<string, mixed>
     */
    public function applyOptionHealth(array $option, int $lowWarning, array $duplicateIbsModels): array
    {
        $ibsModel = (string) ($option['ibs_model'] ?? '');
        $stock = (int) ($option['stock'] ?? 0);
        $image = $option['image'] ?? null;
        $isDuplicate = $ibsModel !== '' && in_array($ibsModel, $duplicateIbsModels, true);
        $optionLow = $this->optionLowWarning($option, $lowWarning);
        $ibsStock = $this->nullableInt($option['ibs_stock'] ?? null);
        $rate = array_key_exists('rate', $option) && $option['rate'] !== null
            ? (float) $option['rate']
            : null;

        $coreHealths = [
            $this->assessNegativeStock($stock, $ibsStock),
            $this->assessLocalHealth($rate, $ibsStock, $optionLow, true),
            $this->assessIbsModelHealth($ibsModel),
        ];

        if ($isDuplicate) {
            $coreHealths[] = $this->healthResult('needs_attention', 'Review', ['Duplicate IBS model']);
        }

        $qualityIssues = $this->collectQualityIssues(
            image: is_string($image) ? $image : null,
            isOption: true,
            isDuplicateIbs: false,
        );

        return array_merge($option, [
            'health' => $this->composeHealth($coreHealths, $qualityIssues),
        ]);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, string>  $duplicateIbsModels
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    public function assessParentHealth(
        array $product,
        array $options,
        int $lowWarning,
        array $duplicateIbsModels,
    ): array {
        $ibsModel = (string) ($product['ibs_model'] ?? '');
        $stock = (int) ($product['stock'] ?? 0);
        $image = $product['image'] ?? null;
        $isDuplicate = $ibsModel !== '' && in_array($ibsModel, $duplicateIbsModels, true);
        $isVariable = count($options) > 0;
        $rate = array_key_exists('rate', $product) && $product['rate'] !== null
            ? (float) $product['rate']
            : null;
        $ibsStock = $this->nullableInt($product['ibs_stock'] ?? null);
        $category = trim((string) ($product['product_category'] ?? ''));

        $coreHealths = [
            $this->assessCategoryHealth($category),
            $this->assessIbsModelHealth($ibsModel),
        ];

        if ($isDuplicate) {
            $coreHealths[] = $this->healthResult('needs_attention', 'Review', ['Duplicate IBS model']);
        }

        if ($isVariable) {
            $coreHealths[] = $this->assessVariableReadiness($product, $options, $lowWarning);
            $aggregatedIbsStock = ProductIbsStockAggregator::forProduct(
                array_merge($product, ['options' => $options])
            );
            $parentOcStock = $this->resolveVariableParentStock($options, $stock);
            $coreHealths[] = $this->assessNegativeStock($parentOcStock, $aggregatedIbsStock);

            foreach ($options as $option) {
                $optionIbsStock = $this->nullableInt($option['ibs_stock'] ?? null);
                $coreHealths[] = $this->assessNegativeStock((int) ($option['stock'] ?? 0), $optionIbsStock);
            }
        } else {
            $coreHealths[] = $this->assessNegativeStock($stock, $ibsStock);
            $coreHealths[] = $this->assessLocalHealth($rate, $ibsStock, $lowWarning, true);
        }

        $qualityIssues = $this->collectQualityIssues(
            image: is_string($image) ? $image : null,
            isOption: false,
            isDuplicateIbs: false,
        );

        if ($isVariable) {
            foreach ($options as $option) {
                $variantDuplicate = in_array((string) ($option['ibs_model'] ?? ''), $duplicateIbsModels, true);

                if ($variantDuplicate) {
                    $coreHealths[] = $this->healthResult('needs_attention', 'Review', ['Duplicate IBS model']);
                }

                array_push(
                    $qualityIssues,
                    ...$this->collectQualityIssues(
                        image: is_string($option['image'] ?? null) ? $option['image'] : null,
                        isOption: true,
                        isDuplicateIbs: false,
                    )
                );
            }
        }

        return $this->composeHealth($coreHealths, $qualityIssues);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $options
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessVariableReadiness(array $product, array $options, int $lowWarning): array
    {
        if ($options === []) {
            return $this->healthResult('ok', 'OK', []);
        }

        $parentRate = array_key_exists('rate', $product) && $product['rate'] !== null
            ? (float) $product['rate']
            : null;

        $readyCount = 0;
        $missingRateCount = 0;
        $missingStockCount = 0;

        foreach ($options as $option) {
            $variantRate = array_key_exists('rate', $option) && $option['rate'] !== null
                ? (float) $option['rate']
                : null;
            $effectiveRate = $variantRate ?? $parentRate;
            $ibsStock = $this->nullableInt($option['ibs_stock'] ?? null);
            $optionLow = $this->optionLowWarning($option, $lowWarning);

            if ($effectiveRate === null) {
                $missingRateCount++;
            }

            if ($ibsStock === null) {
                $missingStockCount++;
            }

            if ($effectiveRate === null || $ibsStock === null) {
                continue;
            }

            if ($ibsStock < 0) {
                continue;
            }

            if ($ibsStock < $optionLow) {
                return $this->healthResult('alert', 'Alert', ['Low stock']);
            }

            $readyCount++;
        }

        if ($readyCount === count($options)) {
            return $this->healthResult('ok', 'OK', []);
        }

        if ($readyCount > 0) {
            return $this->healthResult('needs_attention', 'Review', ['Some variants missing rate or stock']);
        }

        if ($missingRateCount === count($options)) {
            return $this->healthResult('critical', 'Critical', ['Missing Rate']);
        }

        if ($missingStockCount === count($options)) {
            return $this->healthResult('warning', 'Warning', ['Missing IBS Stock']);
        }

        return $this->healthResult('needs_attention', 'Review', ['Some variants missing rate or stock']);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessCategoryHealth(string $category): array
    {
        if ($category !== '') {
            return $this->healthResult('ok', 'OK', []);
        }

        return $this->healthResult('warning', 'Warning', ['Missing category']);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessIbsModelHealth(string $ibsModel): array
    {
        if (trim($ibsModel) !== '') {
            return $this->healthResult('ok', 'OK', []);
        }

        return $this->healthResult('needs_attention', 'Review', ['Missing IBS model']);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessNegativeStock(int $ocStock, ?int $ibsStock): array
    {
        $issues = [];

        if ($ocStock < 0) {
            $issues[] = 'Negative Stock -';
        }

        if ($ibsStock !== null && $ibsStock < 0) {
            $issues[] = 'Negative Stock -';
        }

        if ($issues !== []) {
            return $this->healthResult('critical', 'Critical', array_values(array_unique($issues)));
        }

        return $this->healthResult('ok', 'OK', []);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessLocalHealth(
        ?float $rate,
        ?int $ibsStock,
        int $lowWarning,
        bool $checkRate,
    ): array {
        if ($checkRate && $rate === null) {
            return $this->healthResult('critical', 'Critical', ['Missing Rate']);
        }

        if ($ibsStock === null) {
            return $this->healthResult('warning', 'Warning', ['Missing IBS Stock']);
        }

        if ($ibsStock < $lowWarning) {
            return $this->healthResult('alert', 'Alert', ['Low stock']);
        }

        return $this->healthResult('ok', 'OK', []);
    }

    /**
     * @return array<int, string>
     */
    protected function collectQualityIssues(?string $image, bool $isOption, bool $isDuplicateIbs = false): array
    {
        $issues = [];

        if (blank($image)) {
            $issues[] = $isOption ? 'Missing option image' : 'Missing main image';
        }

        return $issues;
    }

    /**
     * @param  array<int, array{status: string, label: string, issues: array<int, string>}>  $coreHealths
     * @param  array<int, string>  $qualityIssues
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function composeHealth(array $coreHealths, array $qualityIssues): array
    {
        $core = $this->mergeHealthPriority(...$coreHealths);
        $allIssues = array_merge($core['issues'] ?? [], $qualityIssues);

        return [
            'status' => $core['status'],
            'label' => $core['label'],
            'issues' => array_values(array_unique($allIssues)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    protected function resolveVariableParentStock(array $options, int $parentStock): int
    {
        if ($options === []) {
            return $parentStock;
        }

        $stocks = array_map(fn (array $option) => (int) ($option['stock'] ?? 0), $options);

        return min($stocks);
    }

    /**
     * @param  array<string, mixed>  $option
     */
    public function optionLowWarning(array $option, int $parentLowWarning): int
    {
        if (array_key_exists('low_warning', $option) && $option['low_warning'] !== null) {
            return (int) $option['low_warning'];
        }

        return $parentLowWarning;
    }

    /**
     * @param  array{status: string, label: string, issues: array<int, string>}  ...$healths
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    public function mergeHealthPriority(array ...$healths): array
    {
        $winner = $this->healthResult('ok', 'OK', []);
        $winnerPriority = 1;
        $allIssues = [];

        foreach ($healths as $health) {
            $status = $health['status'] ?? 'ok';
            $priority = self::HEALTH_PRIORITY[$status] ?? 1;

            if ($priority > $winnerPriority) {
                $winnerPriority = $priority;
                $winner = $health;
            }

            foreach ($health['issues'] ?? [] as $issue) {
                $allIssues[] = $issue;
            }
        }

        if ($winnerPriority <= 1) {
            return $this->healthResult('ok', 'OK', array_values(array_unique($allIssues)));
        }

        $status = $winner['status'] ?? 'ok';
        $label = $winner['label'] ?? 'OK';

        if ($status === 'low') {
            $status = 'alert';
            $label = 'Alert';
        }

        return [
            'status' => $status,
            'label' => $label,
            'issues' => array_values(array_unique($allIssues)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{health_ok: int, health_needs_work: int, variant_rows: int}
     */
    public function buildDashboardCounts(array $products): array
    {
        $ready = 0;
        $variantRows = 0;

        foreach ($products as $product) {
            if (($product['health']['status'] ?? '') === 'ok') {
                $ready++;
            }

            foreach ($product['options'] ?? [] as $option) {
                if (is_array($option)) {
                    $variantRows++;
                }
            }
        }

        $total = count($products);

        return [
            'health_ok' => $ready,
            'health_needs_work' => max(0, $total - $ready),
            'variant_rows' => $variantRows,
        ];
    }

    /**
     * @param  array<int, string>  $issues
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function healthResult(string $status, string $label, array $issues): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
        ];
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
