<?php

namespace App\Services\ProductMap;

class ProductMapListingFilter
{
    /**
     * @return array{q: string, category: string, type: string, health: string, per_page: int}
     */
    public function resolveFromRequest(\Illuminate\Http\Request $request): array
    {
        $allowedPerPage = [10, 20, 50];
        $defaultPerPage = min(50, max(1, (int) config('dropflow.product_preview_page_size', 20)));
        $perPage = (int) $request->query('per_page', $defaultPerPage);

        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = in_array($defaultPerPage, $allowedPerPage, true) ? $defaultPerPage : 20;
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'type' => $this->normalizeType((string) $request->query('type', 'all')),
            'health' => $this->normalizeHealth((string) $request->query('health', 'all')),
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array{q?: string, category?: string, type?: string, health?: string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $products, array $filters): array
    {
        $filtered = [];

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                continue;
            }

            if ($this->matches($product, $filters)) {
                $filtered[$index] = $product;
            }
        }

        return $filtered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    public function categoryOptions(array $products, array $storedCategories = []): array
    {
        $categories = [];

        foreach ($storedCategories as $category) {
            $category = trim((string) $category);
            if ($category !== '') {
                $categories[$category] = true;
            }
        }

        foreach ($products as $product) {
            $category = trim((string) ($product['product_category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = true;
            }
        }

        $list = array_keys($categories);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param  array{q?: string, category?: string, type?: string, health?: string, per_page?: int}  $filters
     * @return array<string, string|int>
     */
    public function queryParams(array $filters, ?int $page = null): array
    {
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $params['q'] = $filters['q'];
        }

        if (($filters['category'] ?? '') !== '') {
            $params['category'] = $filters['category'];
        }

        if (($filters['type'] ?? 'all') !== 'all') {
            $params['type'] = $filters['type'];
        }

        if (($filters['health'] ?? 'all') !== 'all') {
            $params['health'] = $filters['health'];
        }

        $defaultPerPage = min(50, max(1, (int) config('dropflow.product_preview_page_size', 20)));
        if ((int) ($filters['per_page'] ?? $defaultPerPage) !== $defaultPerPage) {
            $params['per_page'] = (int) $filters['per_page'];
        }

        if ($page !== null && $page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array{q?: string, category?: string, type?: string, health?: string}  $filters
     */
    protected function matches(array $product, array $filters): bool
    {
        $q = mb_strtolower((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) ($product['product_id'] ?? $product['oc_product_id'] ?? ''),
                (string) ($product['model'] ?? $product['lk_model'] ?? ''),
                (string) ($product['ibs_model'] ?? ''),
                (string) ($product['sm_model'] ?? ''),
                (string) ($product['name'] ?? ''),
            ])));

            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && trim((string) ($product['product_category'] ?? '')) !== $category) {
            return false;
        }

        $type = $filters['type'] ?? 'all';
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $isVariable = count($options) > 0;

        if ($type === 'simple' && $isVariable) {
            return false;
        }

        if ($type === 'variable' && ! $isVariable) {
            return false;
        }

        $health = $filters['health'] ?? 'all';
        $status = (string) (($product['health'] ?? [])['status'] ?? 'ok');

        if ($health === 'ok' && $status !== 'ok') {
            return false;
        }

        if ($health === 'needs' && $status === 'ok') {
            return false;
        }

        return true;
    }

    protected function normalizeType(string $type): string
    {
        return in_array($type, ['all', 'simple', 'variable'], true) ? $type : 'all';
    }

    protected function normalizeHealth(string $health): string
    {
        return in_array($health, ['all', 'ok', 'needs'], true) ? $health : 'all';
    }
}
