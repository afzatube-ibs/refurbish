<?php

namespace App\Services\OrderMap;

use App\Models\Connection;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductMapProduct;
use App\Models\Supplier;
use RuntimeException;

class ManualOrderProductSearchService
{
    /**
     * @return list<array{
     *     source_product_id: string,
     *     product_name: string,
     *     model: string,
     *     ibs_model: ?string,
     *     sm_model: ?string,
     *     image: string,
     *     supplier_cost: ?float,
     *     option_label: string
     * }>
     */
    public function search(string $query, int $limit = 15): array
    {
        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        $supplier = $this->resolveSupplier(Connection::getInstance());

        $controlStates = ProductControlState::query()
            ->where('supplier_id', $supplier->id)
            ->with('variants')
            ->get()
            ->keyBy('source_product_id');

        $catalogRows = ProductMapProduct::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('source_product_id')
            ->get();

        $results = [];

        foreach ($catalogRows as $row) {
            $snapshot = is_array($row->oc_snapshot) ? $row->oc_snapshot : [];
            $productId = (string) $row->source_product_id;
            $state = $controlStates->get($productId);

            $name = trim((string) ($snapshot['name'] ?? ''));
            $lkModel = trim((string) ($snapshot['model'] ?? $snapshot['lk_model'] ?? ''));
            $ibsModel = trim((string) ($state?->ibs_model ?? $snapshot['ibs_model'] ?? ''));
            $smModel = trim((string) ($state?->sm_model ?? ''));

            if (! $this->matchesNeedle($needle, [$productId, $name, $lkModel, $ibsModel, $smModel])) {
                continue;
            }

            $rate = $state?->rate !== null ? (float) $state->rate : null;
            $image = trim((string) ($snapshot['image'] ?? $snapshot['image_path'] ?? ''));

            $results[] = [
                'source_product_id' => $productId,
                'product_name' => $name !== '' ? $name : 'Product #'.$productId,
                'model' => $lkModel !== '' ? $lkModel : ($ibsModel !== '' ? $ibsModel : $productId),
                'ibs_model' => $ibsModel !== '' ? $ibsModel : null,
                'sm_model' => $smModel !== '' ? $smModel : null,
                'image' => $image,
                'supplier_cost' => $rate,
                'option_label' => '',
            ];

            if (count($results) >= $limit) {
                return $results;
            }
        }

        foreach ($controlStates as $state) {
            if ($catalogRows->firstWhere('source_product_id', $state->source_product_id)) {
                continue;
            }

            $productId = (string) $state->source_product_id;
            $ibsModel = trim((string) ($state->ibs_model ?? ''));
            $smModel = trim((string) ($state->sm_model ?? ''));

            if (! $this->matchesNeedle($needle, [$productId, $ibsModel, $smModel])) {
                continue;
            }

            $results[] = [
                'source_product_id' => $productId,
                'product_name' => 'Product #'.$productId,
                'model' => $ibsModel !== '' ? $ibsModel : $productId,
                'ibs_model' => $ibsModel !== '' ? $ibsModel : null,
                'sm_model' => $smModel !== '' ? $smModel : null,
                'image' => '',
                'supplier_cost' => $state->rate !== null ? (float) $state->rate : null,
                'option_label' => '',
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param  list<string>  $fields
     */
    protected function matchesNeedle(string $needle, array $fields): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter($fields, fn ($value) => trim($value) !== '')));

        return $haystack !== '' && str_contains($haystack, $needle);
    }

    protected function resolveSupplier(Connection $connection): Supplier
    {
        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($connection) {
                $query->where('code', $connection->supplier_filter)
                    ->orWhere('code', strtoupper((string) $connection->supplier_filter));
            })
            ->first();

        if ($supplier) {
            return $supplier;
        }

        $fallback = Supplier::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new RuntimeException('No active supplier configured for product search.');
        }

        return $fallback;
    }
}
