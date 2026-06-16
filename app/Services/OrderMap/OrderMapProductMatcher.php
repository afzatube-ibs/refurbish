<?php

namespace App\Services\OrderMap;

use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductControlVariant;
use App\Models\Supplier;
use App\Services\ProductMap\ProductControlPersistenceService;

class OrderMapProductMatcher
{
    /**
     * @param  array<string, mixed>  $itemData
     * @return array{
     *     matched: bool,
     *     state: ?ProductControlState,
     *     variant: ?ProductControlVariant,
     *     variant_key: string,
     *     supplier_cost: ?float
     * }
     */
    public function match(Supplier $supplier, array $itemData): array
    {
        $sourceProductId = trim((string) ($itemData['source_product_id'] ?? $itemData['product_id'] ?? ''));
        $model = trim((string) ($itemData['model'] ?? $itemData['option_model'] ?? ''));
        $variantKey = $model !== '' ? $model : ProductControlPersistenceService::SIMPLE_STOCK_KEY;

        if ($sourceProductId === '') {
            return $this->unmatched(null, null, $variantKey);
        }

        $state = ProductControlState::query()
            ->where('supplier_id', $supplier->id)
            ->where('source_product_id', $sourceProductId)
            ->with('variants')
            ->first();

        if (! $state) {
            return $this->unmatched(null, null, $variantKey);
        }

        $variant = $state->variants->firstWhere('source_variant_key', $variantKey);

        if (! $variant && $state->variants->count() === 1) {
            $variant = $state->variants->first();
            $variantKey = (string) $variant->source_variant_key;
        }

        if (! $variant && $variantKey === ProductControlPersistenceService::SIMPLE_STOCK_KEY) {
            $variant = $state->variants->firstWhere('source_variant_key', ProductControlPersistenceService::SIMPLE_STOCK_KEY);
        }

        if (! $variant && $state->variants->isEmpty()) {
            return [
                'matched' => true,
                'state' => $state,
                'variant' => null,
                'variant_key' => ProductControlPersistenceService::SIMPLE_STOCK_KEY,
                'supplier_cost' => $state->rate !== null ? (float) $state->rate : null,
            ];
        }

        if (! $variant) {
            return $this->unmatched($state, null, $variantKey);
        }

        $cost = $variant->rate !== null ? (float) $variant->rate : ($state->rate !== null ? (float) $state->rate : null);

        return [
            'matched' => true,
            'state' => $state,
            'variant' => $variant,
            'variant_key' => $variantKey,
            'supplier_cost' => $cost,
        ];
    }

    /**
     * @return array{
     *     matched: bool,
     *     state: ?ProductControlState,
     *     variant: ?ProductControlVariant,
     *     variant_key: string,
     *     supplier_cost: ?float
     * }
     */
    protected function unmatched(?ProductControlState $state, ?ProductControlVariant $variant, string $variantKey): array
    {
        return [
            'matched' => false,
            'state' => $state,
            'variant' => $variant,
            'variant_key' => $variantKey,
            'supplier_cost' => null,
        ];
    }
}
