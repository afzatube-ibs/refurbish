<?php

namespace App\Services\ProductMap;

use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductRateHistory;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OpenCart\ProductPreviewService;
use Illuminate\Support\Collection;

class ProductControlMergeService
{
    public function __construct(
        private readonly ProductControlSupplierResolver $supplierResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function mergeIntoPreview(array $preview): array
    {
        $products = is_array($preview['products'] ?? null) ? $preview['products'] : [];

        if ($products === []) {
            return $preview;
        }

        $supplier = $this->supplierResolver->resolve();
        $productIds = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $id = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');

            if ($id !== '') {
                $productIds[] = $id;
            }
        }

        if ($productIds === []) {
            return $preview;
        }

        $states = ProductControlState::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('source_product_id', array_values(array_unique($productIds)))
            ->with('variants')
            ->get()
            ->keyBy('source_product_id');

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                continue;
            }

            $id = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');

            if ($id === '' || ! $states->has($id)) {
                continue;
            }

            $products[$index] = $this->mergeProduct($product, $states->get($id));
        }

        $preview['products'] = $products;
        $preview['meta'] = is_array($preview['meta'] ?? null) ? $preview['meta'] : [];
        $preview['meta']['has_local_edits'] = $states->isNotEmpty();

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function mergeProduct(array $product, ProductControlState $state): array
    {
        if ($state->ibs_model !== null && $state->ibs_model !== '') {
            $product['ibs_model'] = $state->ibs_model;
        }

        if ($state->sm_model !== null) {
            $product['sm_model'] = $state->sm_model;
        }

        if ($state->product_category !== null && $state->product_category !== '') {
            $product['product_category'] = $state->product_category;
        }

        if ($state->rate !== null) {
            $product['rate'] = (float) $state->rate;
            $product['supplier_cost'] = (float) $state->rate;
        }

        if ($state->low_warning !== null) {
            $product['low_warning'] = (int) $state->low_warning;
        }

        $variantsByKey = $state->variants->keyBy('source_variant_key');
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $parentRate = $state->rate !== null ? (float) $state->rate : null;

        if ($options === []) {
            $simpleStock = $variantsByKey->get('__simple__');
            if ($simpleStock && $simpleStock->ibs_stock !== null) {
                $product['ibs_stock'] = (int) $simpleStock->ibs_stock;
            }
        }

        foreach ($options as $variantIndex => $option) {
            if (! is_array($option)) {
                continue;
            }

            $key = $this->variantKey($option, (int) $variantIndex);
            $stored = $variantsByKey->get($key);
            $override = ($stored && $stored->rate !== null) ? (float) $stored->rate : null;

            if ($stored) {
                if ($stored->ibs_model !== null && $stored->ibs_model !== '') {
                    $option['ibs_model'] = $stored->ibs_model;
                }

                if ($stored->sm_model !== null) {
                    $option['sm_model'] = $stored->sm_model;
                }

                if ($stored->ibs_stock !== null) {
                    $option['ibs_stock'] = (int) $stored->ibs_stock;
                }

                if ($stored->low_warning !== null) {
                    $option['low_warning'] = (int) $stored->low_warning;
                }
            }

            $option['rate_override'] = $override;
            $option['rate'] = $override ?? $parentRate;

            $options[$variantIndex] = $option;
        }

        $product['options'] = $options;
        $product['variants'] = $options;

        return $product;
    }

    /**
     * @param  array<string, mixed>  $option
     */
    public function variantKey(array $option, int $variantIndex): string
    {
        $model = trim((string) ($option['lk_model'] ?? $option['model'] ?? ''));

        if ($model !== '' && $model !== '—') {
            return $model;
        }

        return 'variant-'.$variantIndex;
    }

    /**
     * @return array{rate: Collection<int, ProductRateHistory>, stock: Collection<int, StockAdjustmentHistory>}
     */
    public function historyForProduct(Supplier $supplier, string $productId): array
    {
        return [
            'rate' => ProductRateHistory::query()
                ->with('changedByUser')
                ->where('supplier_id', $supplier->id)
                ->where('product_id', $productId)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->get(),
            'stock' => StockAdjustmentHistory::query()
                ->with('changedByUser')
                ->where('supplier_id', $supplier->id)
                ->where('product_id', $productId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function historyCount(Supplier $supplier, string $productId): int
    {
        $rateCount = ProductRateHistory::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $productId)
            ->count();

        $stockCount = StockAdjustmentHistory::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $productId)
            ->count();

        return $rateCount + $stockCount;
    }
}
