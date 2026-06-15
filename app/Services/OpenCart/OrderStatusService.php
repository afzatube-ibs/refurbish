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
     * @return array<int, array{id: int, name: string}>
     */
    public function fetchFromOpenCart(): array
    {
        app(ConnectionService::class)->assertSyncAllowed();

        $connection = Connection::getInstance();
        $response = $this->client->get($connection->order_status_api_endpoint);
        $statuses = $response['statuses'] ?? [];

        foreach ($statuses as $status) {
            $statusId = (int) ($status['id'] ?? 0);
            $statusName = (string) ($status['name'] ?? '');

            if ($statusId <= 0) {
                continue;
            }

            $mapping = OrderStatusMapping::query()->firstOrNew([
                'source_status_id' => $statusId,
            ]);
            $mapping->source_status_name = $statusName;

            if (! $mapping->exists) {
                $mapping->sfm_status = SfmOrderStatus::Ignore;
            }

            $mapping->save();
        }

        return $statuses;
    }

    public function applyMapping(int $statusId): SfmOrderStatus
    {
        $mapping = OrderStatusMapping::query()
            ->where('source_status_id', $statusId)
            ->first();

        return $mapping?->sfm_status ?? SfmOrderStatus::Ignore;
    }
}
