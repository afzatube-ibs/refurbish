<?php

namespace App\Services\OrderMap;

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use App\Models\OrderStatusMapping;

class OrderStatusMappingGuide
{
    /**
     * Recommended OC status ID → IBS status for live queue.
     *
     * @var array<int, SfmOrderStatus>
     */
    public const RECOMMENDED_BY_OC_ID = [
        25 => SfmOrderStatus::New,
        7 => SfmOrderStatus::Rejected,
        11 => SfmOrderStatus::ReturnQueue,
        43 => SfmOrderStatus::ReturnReceived,
        5 => SfmOrderStatus::Completed,
    ];

    /**
     * @return list<array{oc_id: int, oc_name: string, ibs: SfmOrderStatus, sync_role: OrderSyncRole, behavior: string}>
     */
    public function recommendedRows(): array
    {
        $names = [
            25 => 'From Warehouse',
            7 => 'Canceled',
            11 => 'R-Returning',
            43 => 'R-Return-Warehouse',
            5 => 'Complete',
        ];

        $rows = [];
        foreach (self::RECOMMENDED_BY_OC_ID as $ocId => $ibs) {
            $role = OrderSyncRole::recommendedFor($ibs);
            $rows[] = [
                'oc_id' => $ocId,
                'oc_name' => $names[$ocId] ?? 'Status '.$ocId,
                'ibs' => $ibs,
                'sync_role' => $role,
                'behavior' => $this->syncBehaviorLabel($ibs, $role),
            ];
        }

        return $rows;
    }

    public function ibsBadgeLabel(SfmOrderStatus $status): string
    {
        return match ($status) {
            SfmOrderStatus::New => 'Create Order',
            SfmOrderStatus::Rejected => 'Restore Stock',
            SfmOrderStatus::ReturnQueue => 'Returning',
            SfmOrderStatus::ReturnReceived => 'Return Received',
            SfmOrderStatus::Completed => 'Final',
            SfmOrderStatus::Ignore => 'Inactive',
            default => $status->label(),
        };
    }

    public function syncBehaviorLabel(SfmOrderStatus $status, ?OrderSyncRole $role = null): string
    {
        $role ??= OrderSyncRole::recommendedFor($status);

        if ($role === OrderSyncRole::Ignore) {
            return 'No import/update';
        }

        return match (true) {
            $role === OrderSyncRole::ImportTrigger && $status === SfmOrderStatus::New => 'Create IBS order once + deduct stock',
            $role === OrderSyncRole::UpdateExisting && $status === SfmOrderStatus::Rejected => 'Update existing order + restore stock',
            $role === OrderSyncRole::UpdateExisting && $status === SfmOrderStatus::Completed => 'Update existing order to completed',
            $role === OrderSyncRole::UpdateExisting && $status === SfmOrderStatus::ReturnQueue => 'Mark existing order as returning',
            $role === OrderSyncRole::UpdateExisting && $status === SfmOrderStatus::ReturnReceived => 'Mark existing order as return received',
            $role === OrderSyncRole::UpdateExisting => 'Update existing order only',
            default => 'Workflow status',
        };
    }

    public function helperText(SfmOrderStatus $status, ?OrderSyncRole $role = null): string
    {
        $role ??= OrderSyncRole::recommendedFor($status);

        if ($role === OrderSyncRole::ImportTrigger) {
            return 'Creates IBS order once and deducts stock for matched products.';
        }

        if ($role === OrderSyncRole::UpdateExisting) {
            return match ($status) {
                SfmOrderStatus::Rejected => 'Updates existing order only and restores stock.',
                SfmOrderStatus::ReturnQueue => 'Updates existing order only. Product is returning, not received yet.',
                SfmOrderStatus::ReturnReceived => 'Updates existing order only. Product physically received back.',
                SfmOrderStatus::Completed => 'Updates existing order only. Final delivered.',
                default => 'Updates existing order only — never creates new orders.',
            };
        }

        return 'No import/update.';
    }

    public function ibsBadgeClass(SfmOrderStatus $status): string
    {
        return match ($status) {
            SfmOrderStatus::New => 'osm-badge-new',
            SfmOrderStatus::Rejected => 'osm-badge-rejected',
            SfmOrderStatus::ReturnQueue => 'osm-badge-return-queue',
            SfmOrderStatus::ReturnReceived => 'osm-badge-return-received',
            SfmOrderStatus::Completed => 'osm-badge-completed',
            SfmOrderStatus::Ignore => 'osm-badge-ignore',
            default => 'osm-badge-default',
        };
    }

    public function recommendedFor(OrderStatusMapping $mapping): ?SfmOrderStatus
    {
        return self::RECOMMENDED_BY_OC_ID[$mapping->source_status_id] ?? null;
    }

    public function recommendedSyncRoleFor(OrderStatusMapping $mapping): OrderSyncRole
    {
        $recommended = $this->recommendedFor($mapping);

        return $recommended ? OrderSyncRole::recommendedFor($recommended) : OrderSyncRole::Ignore;
    }

    public function isDangerousSelection(OrderStatusMapping $mapping, string $selectedValue): bool
    {
        if (! $mapping->oc_selected) {
            return false;
        }

        $recommended = $this->recommendedFor($mapping);

        if ($recommended === null) {
            return false;
        }

        return $selectedValue !== $recommended->value;
    }

    /**
     * @param  array<int, array{id: int, sfm_status: string}>  $submitted
     * @return list<array{id: int, name: string, recommended: string, selected: string}>
     */
    public function dangerousChanges(array $submitted): array
    {
        $byId = OrderStatusMapping::query()->get()->keyBy('id');
        $changes = [];

        foreach ($submitted as $row) {
            $mapping = $byId->get($row['id'] ?? 0);

            if (! $mapping instanceof OrderStatusMapping) {
                continue;
            }

            $selected = (string) ($row['sfm_status'] ?? '');

            if (! $this->isDangerousSelection($mapping, $selected)) {
                continue;
            }

            $recommended = $this->recommendedFor($mapping);

            $changes[] = [
                'id' => $mapping->id,
                'name' => $mapping->source_status_name,
                'recommended' => $recommended?->label() ?? '—',
                'selected' => SfmOrderStatus::tryFrom($selected)?->label() ?? $selected,
            ];
        }

        return $changes;
    }
}
