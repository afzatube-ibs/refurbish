<?php

namespace App\Services\ProductMap;

use App\Models\User;
use App\Services\OpenCart\ProductPreviewService;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ProductMapLocalControlService
{
    public const STOCK_REASONS = [
        'Sent to Wholesale',
        'Correction',
    ];

    public function __construct(
        private readonly ProductPreviewService $previewService,
        private readonly ProductControlPersistenceService $persistence,
        private readonly ProductControlMergeService $mergeService,
        private readonly ProductControlSupplierResolver $supplierResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $preview, int $productIndex, array $payload, User $user): array
    {
        $products = $preview['products'] ?? [];

        if (! isset($products[$productIndex]) || ! is_array($products[$productIndex])) {
            throw new InvalidArgumentException('Product not found in preview.');
        }

        $product = $products[$productIndex];
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];

        if ($changes === []) {
            throw new InvalidArgumentException('No changes to save.');
        }

        $this->persistence->applyChanges($product, $changes, $user);

        $merged = $this->mergeService->mergeIntoPreview([
            'products' => [$product],
            'meta' => $preview['meta'] ?? [],
        ]);

        $products[$productIndex] = $merged['products'][0] ?? $product;
        $preview['products'] = $products;
        $preview['meta'] = is_array($preview['meta'] ?? null) ? $preview['meta'] : [];
        $preview['meta']['has_local_edits'] = true;

        return $this->previewService->refreshPreviewState($preview);
    }

    /**
     * @return array{rate: array<int, array<string, mixed>>, stock: array<int, array<string, mixed>>}
     */
    public function historyForProduct(string $productId): array
    {
        $supplier = $this->supplierResolver->resolve();
        $history = $this->mergeService->historyForProduct($supplier, $productId);

        return [
            'rate' => $history['rate']->map(function ($row) {
                $moment = $row->effective_from ?? $row->created_at;
                $formatted = $this->formatHistoryMoment($moment);

                return [
                    'id' => $row->id,
                    'product_id' => $row->product_id,
                    'variant_id' => $row->variant_id,
                    'old_rate' => $row->old_rate,
                    'new_rate' => $row->new_rate,
                    'difference' => $row->difference,
                    'date' => $formatted['date'],
                    'time' => $formatted['time'],
                    'sort_at' => $moment?->toIso8601String() ?? '',
                    'note' => $row->note,
                    'user' => $this->resolveHistoryUser($row->changedByUser),
                ];
            })->all(),
            'stock' => $history['stock']->map(function ($row) {
                $moment = $row->created_at;
                $formatted = $this->formatHistoryMoment($moment);

                return [
                    'id' => $row->id,
                    'product_id' => $row->product_id,
                    'variant_id' => $row->variant_id,
                    'old_stock' => $row->old_stock,
                    'new_stock' => $row->new_stock,
                    'difference' => $row->difference,
                    'reason' => $row->reason,
                    'note' => $row->note,
                    'date' => $formatted['date'],
                    'time' => $formatted['time'],
                    'sort_at' => $moment?->toIso8601String() ?? '',
                    'user' => $this->resolveHistoryUser($row->changedByUser),
                ];
            })->all(),
        ];
    }

    /**
     * @return array{date: string, time: string}
     */
    private function formatHistoryMoment(?CarbonInterface $moment): array
    {
        if ($moment === null) {
            return ['date' => '—', 'time' => '—'];
        }

        return [
            'date' => $moment->format('j M Y'),
            'time' => $moment->format('h:i A'),
        ];
    }

    private function resolveHistoryUser(?User $user): string
    {
        $name = trim((string) ($user?->name ?? ''));

        return $name !== '' ? $name : 'System';
    }

    public function historyCount(string $productId): int
    {
        return $this->mergeService->historyCount(
            $this->supplierResolver->resolve(),
            $productId,
        );
    }
}
