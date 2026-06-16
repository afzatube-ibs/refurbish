<?php

namespace App\Services\OpenCart;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\OrderStatusMapping;

class OrderStatusService
{
    public function __construct(
        protected OpenCartHttpClient $client
    ) {}

    /**
     * @return array{imported: int, selected: int, total: int, statuses: list<array{id: int, name: string, selected: bool}>}
     */
    public function fetchFromOpenCart(): array
    {
        app(ConnectionService::class)->assertSyncAllowed();

        $connection = Connection::getInstance();
        $response = $this->client->get($connection->order_status_api_endpoint);
        $statuses = $this->parseStatusesFromResponse($response);

        foreach ($statuses as $status) {
            $mapping = OrderStatusMapping::query()->firstOrNew([
                'source_status_id' => $status['id'],
            ]);
            $mapping->source_status_name = $status['name'];
            $mapping->oc_selected = $status['selected'];

            if (! $mapping->exists) {
                $mapping->sfm_status = SfmOrderStatus::Ignore;
            }

            $mapping->save();
        }

        $selectedCount = count(array_filter($statuses, fn (array $status) => $status['selected']));

        return [
            'imported' => count($statuses),
            'selected' => $selectedCount,
            'total' => count($statuses),
            'statuses' => $statuses,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array{id: int, name: string, selected: bool}>
     */
    public function parseStatusesFromResponse(array $response): array
    {
        $raw = $this->extractStatusRows($response);
        $parsed = [];

        foreach ($raw as $status) {
            if (! is_array($status)) {
                continue;
            }

            $normalized = $this->normalizeStatusRow($status);

            if ($normalized === null) {
                continue;
            }

            $parsed[$normalized['id']] = $normalized;
        }

        return array_values($parsed);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function extractStatusRows(array $response): array
    {
        foreach (['queue_statuses', 'order_queue_statuses', 'statuses'] as $key) {
            if (is_array($response[$key] ?? null)) {
                return $response[$key];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{id: int, name: string, selected: bool}|null
     */
    protected function normalizeStatusRow(array $status): ?array
    {
        $statusId = (int) ($status['status_id'] ?? $status['id'] ?? 0);
        $statusName = trim((string) ($status['name'] ?? ''));
        $selected = array_key_exists('selected', $status)
            ? filter_var($status['selected'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($statusId <= 0 || $statusName === '') {
            return null;
        }

        return [
            'id' => $statusId,
            'name' => $statusName,
            'selected' => $selected,
        ];
    }

    public function applyMapping(int $statusId): SfmOrderStatus
    {
        $mapping = OrderStatusMapping::query()
            ->where('source_status_id', $statusId)
            ->first();

        return $mapping?->sfm_status ?? SfmOrderStatus::Ignore;
    }
}
