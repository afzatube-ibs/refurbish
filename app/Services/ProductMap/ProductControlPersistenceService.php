<?php

namespace App\Services\ProductMap;

use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductControlVariant;
use App\Models\ProductMap\ProductRateHistory;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductControlPersistenceService
{
    public const STOCK_REASONS = ProductMapLocalControlService::STOCK_REASONS;

    public const SIMPLE_STOCK_KEY = '__simple__';

    public function __construct(
        private readonly ProductControlSupplierResolver $supplierResolver,
        private readonly ProductControlMergeService $mergeService,
    ) {}

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{state: ProductControlState, rate_history: array<int, ProductRateHistory>, stock_history: array<int, StockAdjustmentHistory>}
     */
    public function applyChanges(array $product, array $changes, User $user): array
    {
        $supplier = $this->supplierResolver->resolve();
        $productId = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $isVariable = count($options) > 0;

        if ($productId === '') {
            throw new InvalidArgumentException('Product id missing from preview row.');
        }

        $rateHistory = [];
        $stockHistory = [];

        $state = DB::transaction(function () use ($supplier, $productId, $options, $isVariable, $changes, $user, &$rateHistory, &$stockHistory) {
            $state = ProductControlState::query()->firstOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'source_product_id' => $productId,
                ],
                [
                    'ibs_model' => null,
                    'sm_model' => null,
                    'product_category' => null,
                    'rate' => null,
                    'low_warning' => null,
                ]
            );

            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $scope = (string) ($change['scope'] ?? '');
                $field = (string) ($change['field'] ?? '');

                if ($scope === 'parent') {
                    $this->applyParentChange($state, $field, $change, $productId, $isVariable, $user, $rateHistory, $stockHistory);
                } elseif ($scope === 'variant') {
                    $index = (int) ($change['index'] ?? -1);

                    if (! isset($options[$index]) || ! is_array($options[$index])) {
                        throw new InvalidArgumentException('Variant not found in preview.');
                    }

                    $variantKey = $this->mergeService->variantKey($options[$index], $index);
                    $variant = ProductControlVariant::query()->firstOrCreate(
                        [
                            'product_control_state_id' => $state->id,
                            'source_variant_key' => $variantKey,
                        ],
                        [
                            'ibs_model' => null,
                            'sm_model' => null,
                            'rate' => null,
                            'ibs_stock' => null,
                            'low_warning' => null,
                        ]
                    );

                    $this->applyVariantChange($variant, $field, $change, $productId, $variantKey, $state, $user, $rateHistory, $stockHistory);
                } elseif ($scope === 'simple') {
                    $this->applySimpleScopeChange($state, $field, $change, $productId, $isVariable, $user, $rateHistory, $stockHistory);
                }
            }

            $state->refresh();
            $state->load('variants');

            return $state;
        });

        return [
            'state' => $state,
            'rate_history' => $rateHistory,
            'stock_history' => $stockHistory,
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  array<int, ProductRateHistory>  $rateHistory
     * @param  array<int, StockAdjustmentHistory>  $stockHistory
     */
    protected function applySimpleScopeChange(
        ProductControlState $state,
        string $field,
        array $change,
        string $productId,
        bool $isVariable,
        User $user,
        array &$rateHistory,
        array &$stockHistory,
    ): void {
        if ($isVariable) {
            throw new InvalidArgumentException('Simple scope changes apply to simple products only.');
        }

        if (in_array($field, ['ibs_model', 'sm_model', 'product_category', 'low_warning', 'rate'], true)) {
            $this->applyParentChange($state, $field, $change, $productId, false, $user, $rateHistory, $stockHistory);

            return;
        }

        if ($field === 'ibs_stock') {
            $variant = ProductControlVariant::query()->firstOrCreate(
                [
                    'product_control_state_id' => $state->id,
                    'source_variant_key' => self::SIMPLE_STOCK_KEY,
                ],
                [
                    'ibs_model' => null,
                    'sm_model' => null,
                    'rate' => null,
                    'ibs_stock' => null,
                    'low_warning' => null,
                ]
            );

            $this->applyVariantChange($variant, 'ibs_stock', $change, $productId, self::SIMPLE_STOCK_KEY, $state, $user, $rateHistory, $stockHistory);
        }
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  array<int, ProductRateHistory>  $rateHistory
     * @param  array<int, StockAdjustmentHistory>  $stockHistory
     */
    protected function applyParentChange(
        ProductControlState $state,
        string $field,
        array $change,
        string $productId,
        bool $isVariable,
        User $user,
        array &$rateHistory,
        array &$stockHistory,
    ): void {
        if ($field === 'ibs_model') {
            $state->ibs_model = trim((string) ($change['value'] ?? ''));
            $state->save();

            return;
        }

        if ($field === 'sm_model') {
            $state->sm_model = trim((string) ($change['value'] ?? ''));
            $state->save();

            return;
        }

        if ($field === 'product_category') {
            $raw = trim((string) ($change['value'] ?? ''));
            $state->product_category = $raw !== '' ? $raw : null;
            $state->save();

            return;
        }

        if ($field === 'low_warning') {
            $raw = $change['value'] ?? null;
            $state->low_warning = ($raw === null || $raw === '') ? null : max(0, (int) $raw);
            $state->save();

            return;
        }

        if ($field === 'rate') {
            $newRate = $this->resolveRateChange($state->rate !== null ? (float) $state->rate : null, $change);
            $oldRate = $state->rate !== null ? (float) $state->rate : null;

            if ($this->numericEqual($oldRate, $newRate)) {
                return;
            }

            $state->rate = $newRate;
            $state->save();

            $this->appendRateHistory(
                $rateHistory,
                $state->supplier_id,
                $productId,
                null,
                $oldRate,
                $newRate,
                $user,
                $change,
            );

            return;
        }

        if ($field === 'ibs_stock') {
            if ($isVariable) {
                throw new InvalidArgumentException('Variable products cannot set parent IBS stock.');
            }

            $variant = ProductControlVariant::query()->firstOrCreate(
                [
                    'product_control_state_id' => $state->id,
                    'source_variant_key' => self::SIMPLE_STOCK_KEY,
                ],
                [
                    'ibs_model' => null,
                    'sm_model' => null,
                    'rate' => null,
                    'ibs_stock' => null,
                    'low_warning' => null,
                ]
            );

            $this->applyVariantChange($variant, 'ibs_stock', $change, $productId, self::SIMPLE_STOCK_KEY, $state, $user, $rateHistory, $stockHistory);
        }
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  array<int, ProductRateHistory>  $rateHistory
     * @param  array<int, StockAdjustmentHistory>  $stockHistory
     */
    protected function applyVariantChange(
        ProductControlVariant $variant,
        string $field,
        array $change,
        string $productId,
        string $variantKey,
        ProductControlState $state,
        User $user,
        array &$rateHistory,
        array &$stockHistory,
    ): void {
        if ($field === 'ibs_model') {
            $variant->ibs_model = trim((string) ($change['value'] ?? ''));
            $variant->save();

            return;
        }

        if ($field === 'sm_model') {
            $variant->sm_model = trim((string) ($change['value'] ?? ''));
            $variant->save();

            return;
        }

        if ($field === 'low_warning') {
            $raw = $change['value'] ?? null;
            $variant->low_warning = ($raw === null || $raw === '') ? null : max(0, (int) $raw);
            $variant->save();

            return;
        }

        if ($field === 'rate') {
            $parentRate = $state->rate !== null ? (float) $state->rate : null;
            $oldStored = $variant->rate !== null ? (float) $variant->rate : null;
            $oldEffective = $this->effectiveVariantRate($oldStored, $parentRate);
            $mode = (string) ($change['mode'] ?? 'set');

            if ($mode === 'set' || (array_key_exists('value', $change) && ! array_key_exists('amount', $change))) {
                $newStored = $this->resolveRateChange($oldStored, $change);
            } else {
                $newStored = $this->resolveNumericChange($oldEffective, $change, false);
            }

            $newEffective = $this->effectiveVariantRate($newStored, $parentRate);

            if ($this->numericEqual($oldEffective, $newEffective)) {
                return;
            }

            $variant->rate = $newStored;
            $variant->save();

            $this->appendRateHistory(
                $rateHistory,
                $state->supplier_id,
                $productId,
                $variantKey === self::SIMPLE_STOCK_KEY ? null : $variantKey,
                $oldEffective,
                $newEffective,
                $user,
                $change,
            );

            return;
        }

        if ($field === 'ibs_stock') {
            $oldStock = $variant->ibs_stock;
            $isInitialSet = $this->isInitialStockSet($oldStock, $change);
            $newStock = (int) $this->resolveNumericChange(
                $oldStock !== null ? (float) $oldStock : null,
                $change,
                true,
            );

            if ($this->numericEqual($oldStock, $newStock)) {
                return;
            }

            $reason = trim((string) ($change['reason'] ?? ''));

            if ($isInitialSet) {
                $historyReason = null;
                $diff = $newStock;
            } else {
                if ($reason === '') {
                    throw new InvalidArgumentException('A stock reason is required for IBS Stock changes.');
                }

                if (! in_array($reason, self::STOCK_REASONS, true)) {
                    throw new InvalidArgumentException('Invalid stock reason.');
                }

                $historyReason = $reason;
                $diff = $newStock - (int) ($oldStock ?? 0);
            }

            $variant->ibs_stock = $newStock;
            $variant->save();

            $variant->loadMissing('state');

            $stockHistory[] = StockAdjustmentHistory::query()->create([
                'supplier_id' => $variant->state->supplier_id,
                'product_id' => $productId,
                'variant_id' => $variantKey === self::SIMPLE_STOCK_KEY ? null : $variantKey,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'difference' => $diff,
                'reason' => $historyReason,
                'note' => $this->nullableNote($change),
                'changed_by' => $user->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function isInitialStockSet(?int $oldStock, array $change): bool
    {
        if ($oldStock !== null) {
            return false;
        }

        $mode = (string) ($change['mode'] ?? 'set');

        return $mode === 'set'
            && array_key_exists('value', $change)
            && ! array_key_exists('amount', $change);
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function resolveRateChange(?float $current, array $change): ?float
    {
        $mode = (string) ($change['mode'] ?? 'set');

        if ($mode === 'set' && ($change['value'] === null || $change['value'] === '')) {
            return null;
        }

        return $this->resolveNumericChange($current, $change, false);
    }

    protected function effectiveVariantRate(?float $variantRate, ?float $parentRate): ?float
    {
        if ($variantRate !== null) {
            return $variantRate;
        }

        return $parentRate;
    }

    /**
     * @param  array<int, ProductRateHistory>  $rateHistory
     * @param  array<string, mixed>  $change
     */
    protected function appendRateHistory(
        array &$rateHistory,
        int $supplierId,
        string $productId,
        ?string $variantId,
        ?float $oldRate,
        ?float $newRate,
        User $user,
        array $change,
    ): void {
        $newRateValue = $newRate ?? 0.0;
        $diff = round($newRateValue - ($oldRate ?? 0.0), 2);

        $rateHistory[] = ProductRateHistory::query()->create([
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'old_rate' => $oldRate,
            'new_rate' => $newRateValue,
            'difference' => $diff,
            'effective_from' => now(),
            'changed_by' => $user->id,
            'note' => $this->nullableNote($change),
        ]);
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function resolveNumericChange(?float $current, array $change, bool $asInteger): float
    {
        $mode = (string) ($change['mode'] ?? 'set');

        if ($mode === 'set' || (array_key_exists('value', $change) && ! array_key_exists('amount', $change))) {
            $value = (float) ($change['value'] ?? 0);

            return $asInteger ? (float) max(0, (int) round($value)) : round(max(0, $value), 2);
        }

        $amount = (float) ($change['amount'] ?? 0);
        $base = $current ?? 0.0;

        if ($mode === 'increase') {
            $result = $base + $amount;
        } elseif ($mode === 'decrease') {
            $result = $base - $amount;
        } else {
            throw new InvalidArgumentException('Invalid adjustment mode.');
        }

        if ($asInteger) {
            return (float) max(0, (int) round($result));
        }

        return round(max(0, $result), 2);
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function nullableNote(array $change): ?string
    {
        $note = trim((string) ($change['note'] ?? ''));

        return $note !== '' ? $note : null;
    }

    protected function numericEqual(mixed $left, mixed $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }
}
