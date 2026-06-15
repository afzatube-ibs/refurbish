<?php

namespace App\Services\ProductMap;

use App\Models\User;
use App\Services\OpenCart\ProductPreviewService;
use InvalidArgumentException;

class ProductMapLocalControlService
{
    public const STOCK_REASONS = [
        'Sent to Wholesale',
        'Correction',
    ];

    public function __construct(
        private readonly ProductPreviewService $previewService,
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
        $productId = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');
        $activity = is_array($preview['activity'] ?? null) ? $preview['activity'] : [];
        $entries = [];

        $parentPayload = is_array($payload['parent'] ?? null) ? $payload['parent'] : [];
        $entries = array_merge(
            $entries,
            $this->applyParentChanges($product, $parentPayload, $productId, null, $user)
        );

        $variantsPayload = is_array($payload['variants'] ?? null) ? $payload['variants'] : [];

        foreach ($variantsPayload as $variantPayload) {
            if (! is_array($variantPayload)) {
                continue;
            }

            $variantIndex = (int) ($variantPayload['index'] ?? -1);
            $options = is_array($product['options'] ?? null) ? $product['options'] : [];

            if (! isset($options[$variantIndex]) || ! is_array($options[$variantIndex])) {
                throw new InvalidArgumentException('Variant not found in preview.');
            }

            $variantId = $this->variantId($options[$variantIndex], $variantIndex);
            $entries = array_merge(
                $entries,
                $this->applyVariantChanges($product, $variantIndex, $variantPayload, $productId, $variantId, $user)
            );
        }

        $products[$productIndex] = $product;
        $preview['products'] = $products;
        $preview['activity'] = array_merge($activity, $entries);
        $preview['meta']['has_local_edits'] = true;
        $preview = $this->previewService->refreshPreviewState($preview);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function applyParentChanges(
        array &$product,
        array $payload,
        string $productId,
        ?string $variantId,
        User $user,
    ): array {
        $entries = [];

        if (array_key_exists('ibs_model', $payload)) {
            $entries[] = $this->applyScalarChange(
                $product,
                'ibs_model',
                trim((string) $payload['ibs_model']),
                $productId,
                $variantId,
                $user,
            );
        }

        if (array_key_exists('sm_model', $payload)) {
            $entries[] = $this->applyScalarChange(
                $product,
                'sm_model',
                trim((string) $payload['sm_model']),
                $productId,
                $variantId,
                $user,
            );
        }

        if (array_key_exists('low_warning', $payload)) {
            $entries[] = $this->applyScalarChange(
                $product,
                'low_warning',
                max(0, (int) $payload['low_warning']),
                $productId,
                $variantId,
                $user,
            );
        }

        if (is_array($payload['rate'] ?? null)) {
            $entry = $this->applyNumericChange($product, 'rate', $payload['rate'], $productId, $variantId, $user, false);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if (is_array($payload['ibs_stock'] ?? null)) {
            $entry = $this->applyNumericChange($product, 'ibs_stock', $payload['ibs_stock'], $productId, $variantId, $user, true);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return array_values(array_filter($entries));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function applyVariantChanges(
        array &$product,
        int $variantIndex,
        array $payload,
        string $productId,
        string $variantId,
        User $user,
    ): array {
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $variant = $options[$variantIndex];
        $entries = [];

        if (array_key_exists('ibs_model', $payload)) {
            $entries[] = $this->applyScalarChange(
                $variant,
                'ibs_model',
                trim((string) $payload['ibs_model']),
                $productId,
                $variantId,
                $user,
            );
        }

        if (array_key_exists('sm_model', $payload)) {
            $entries[] = $this->applyScalarChange(
                $variant,
                'sm_model',
                trim((string) $payload['sm_model']),
                $productId,
                $variantId,
                $user,
            );
        }

        if (array_key_exists('low_warning', $payload)) {
            $entries = array_merge($entries, $this->applyVariantLowWarning($variant, $payload['low_warning'], $productId, $variantId, $user));
        }

        if (is_array($payload['rate'] ?? null)) {
            $entry = $this->applyNumericChange($variant, 'rate', $payload['rate'], $productId, $variantId, $user, false);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if (is_array($payload['ibs_stock'] ?? null)) {
            $entry = $this->applyNumericChange($variant, 'ibs_stock', $payload['ibs_stock'], $productId, $variantId, $user, true);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        $options[$variantIndex] = $variant;
        $product['options'] = $options;
        $product['variants'] = $options;

        return array_values(array_filter($entries));
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>|null
     */
    protected function applyScalarChange(
        array &$target,
        string $field,
        mixed $newValue,
        string $productId,
        ?string $variantId,
        User $user,
    ): ?array {
        $oldValue = $target[$field] ?? null;

        if ($this->valuesEqual($oldValue, $newValue)) {
            return null;
        }

        $target[$field] = $newValue;

        return $this->activityEntry(
            productId: $productId,
            variantId: $variantId,
            field: $field,
            oldValue: $oldValue,
            changeType: 'set',
            amount: null,
            newValue: $newValue,
            reason: null,
            user: $user,
        );
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>|null
     */
    protected function applyNumericChange(
        array &$target,
        string $field,
        array $change,
        string $productId,
        ?string $variantId,
        User $user,
        bool $reasonRequired,
    ): ?array {
        $mode = (string) ($change['mode'] ?? '');

        if (! in_array($mode, ['set', 'increase', 'decrease'], true)) {
            return null;
        }

        if (! array_key_exists('amount', $change) && ! array_key_exists('value', $change)) {
            throw new InvalidArgumentException("Amount is required for {$field} {$mode}.");
        }

        $amount = (float) ($change['amount'] ?? $change['value'] ?? 0);
        $oldValue = $target[$field] ?? null;
        $base = $oldValue === null ? 0.0 : (float) $oldValue;

        $newValue = match ($mode) {
            'set' => $amount,
            'increase' => $base + $amount,
            'decrease' => $base - $amount,
            default => $base,
        };

        if ($field === 'ibs_stock') {
            $newValue = (int) round($newValue);
        } else {
            $newValue = round($newValue, 2);
        }

        if ($this->valuesEqual($oldValue, $newValue)) {
            return null;
        }

        $reason = trim((string) ($change['reason'] ?? ''));

        if ($reasonRequired && $reason === '') {
            throw new InvalidArgumentException('A stock reason is required for IBS Stock changes.');
        }

        if ($reasonRequired && ! in_array($reason, self::STOCK_REASONS, true)) {
            throw new InvalidArgumentException('Invalid stock reason.');
        }

        $target[$field] = $newValue;

        return $this->activityEntry(
            productId: $productId,
            variantId: $variantId,
            field: $field,
            oldValue: $oldValue,
            changeType: $mode,
            amount: $amount,
            newValue: $newValue,
            reason: $reason !== '' ? $reason : trim((string) ($change['note'] ?? '')),
            user: $user,
        );
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  mixed  $lowWarningPayload
     * @return array<int, array<string, mixed>>
     */
    protected function applyVariantLowWarning(
        array &$variant,
        mixed $lowWarningPayload,
        string $productId,
        string $variantId,
        User $user,
    ): array {
        if (is_array($lowWarningPayload) && ($lowWarningPayload['inherit'] ?? false)) {
            $oldValue = $variant['low_warning'] ?? null;
            $variant['low_warning'] = null;

            if ($oldValue === null) {
                return [];
            }

            return [
                $this->activityEntry(
                    productId: $productId,
                    variantId: $variantId,
                    field: 'low_warning',
                    oldValue: $oldValue,
                    changeType: 'set',
                    amount: null,
                    newValue: null,
                    reason: 'Inherited from parent',
                    user: $user,
                ),
            ];
        }

        $newValue = max(0, (int) (is_array($lowWarningPayload) ? ($lowWarningPayload['value'] ?? 0) : $lowWarningPayload));

        return array_values(array_filter([
            $this->applyScalarChange($variant, 'low_warning', $newValue, $productId, $variantId, $user),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityEntry(
        string $productId,
        ?string $variantId,
        string $field,
        mixed $oldValue,
        string $changeType,
        mixed $amount,
        mixed $newValue,
        ?string $reason,
        User $user,
    ): array {
        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'field' => $field,
            'old_value' => $oldValue,
            'change_type' => $changeType,
            'amount' => $amount,
            'new_value' => $newValue,
            'reason' => $reason,
            'user' => $user->name,
            'user_id' => $user->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     */
    protected function variantId(array $option, int $variantIndex): string
    {
        $model = trim((string) ($option['lk_model'] ?? $option['model'] ?? ''));

        if ($model !== '' && $model !== '—') {
            return $model;
        }

        return 'variant-'.$variantIndex;
    }

    protected function valuesEqual(mixed $left, mixed $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<int, array<string, mixed>>
     */
    public function activityForProduct(array $preview, string $productId): array
    {
        $activity = is_array($preview['activity'] ?? null) ? $preview['activity'] : [];

        return array_values(array_filter(
            $activity,
            fn (array $entry) => (string) ($entry['product_id'] ?? '') === $productId
        ));
    }
}
