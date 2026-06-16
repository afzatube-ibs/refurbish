<?php

namespace App\Services\OrderMap;

class OrderMapLoadLogService
{
    public const SESSION_KEY = 'order_map_last_sync';

    /**
     * @param  array<string, mixed>  $summary
     */
    public function record(array $summary): void
    {
        session()->put(self::SESSION_KEY, array_merge($summary, [
            'recorded_at' => now()->toIso8601String(),
        ]));
        session()->flash('logs_tab', 'current');
    }

    /**
     * @return array<string, mixed>
     */
    public function last(): array
    {
        $log = session(self::SESSION_KEY);

        return is_array($log) ? $log : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function formatBannerMessage(array $result): string
    {
        return match ($result['mode'] ?? '') {
            'import' => $this->formatImportBanner($result),
            'update' => $this->formatUpdateBanner($result),
            default => 'Order sync complete.',
        };
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function formatImportBanner(array $result): string
    {
        $parts = [
            sprintf('Fetched from OC: %d', (int) ($result['fetched'] ?? 0)),
            sprintf('Imported: %d', (int) ($result['imported'] ?? 0)),
            sprintf('Duplicates skipped: %d', (int) ($result['duplicates_skipped'] ?? 0)),
            sprintf('Update-only skipped: %d', (int) ($result['update_only_skipped'] ?? 0)),
            sprintf('Unmatched product lines: %d', (int) ($result['unmatched_lines'] ?? 0)),
        ];

        $statusIds = $result['requested_status_ids'] ?? [];
        if (is_array($statusIds) && $statusIds !== []) {
            $parts[] = 'status_ids: ['.implode(', ', array_map('intval', $statusIds)).']';
        }

        return 'Load New Orders — '.implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function formatUpdateBanner(array $result): string
    {
        $parts = [
            sprintf('Fetched from OC: %d', (int) ($result['fetched'] ?? 0)),
            sprintf('Updated: %d', (int) ($result['updated'] ?? 0)),
            sprintf('Not found skipped: %d', (int) ($result['not_found_skipped'] ?? 0)),
            sprintf('Locked skipped: %d', (int) ($result['locked_skipped'] ?? 0)),
        ];

        $statusIds = $result['requested_status_ids'] ?? [];
        if (is_array($statusIds) && $statusIds !== []) {
            $parts[] = 'status_ids: ['.implode(', ', array_map('intval', $statusIds)).']';
        }

        return 'Sync Status Updates — '.implode(' · ', $parts);
    }

    public function formatOcStatusLabel(int $statusId, string $statusName): string
    {
        $statusName = trim($statusName);

        if ($statusName !== '' && $statusId > 0) {
            return sprintf('%s (#%d)', $statusName, $statusId);
        }

        if ($statusId > 0) {
            return sprintf('#%d', $statusId);
        }

        return $statusName !== '' ? $statusName : '—';
    }

    public function formatSkipReason(string $reason, string $detail): string
    {
        return match ($reason) {
            'duplicate_existing' => 'Duplicate source_order_id',
            'not_import_trigger', 'update_only_status' => 'Update-only status; not eligible for import',
            'update_only_not_found' => 'Update-only status; order not found in IBS',
            'invalid_import_mapping' => 'Import Trigger requires IBS Status New',
            'locked_status' => 'Order locked; cannot update from source',
            'product_unmatched' => 'ERROR: product unmatched should NOT skip order',
            'missing_order_id' => 'Missing source order id in payload',
            default => $detail !== '' ? $detail : $reason,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsSummary(): array
    {
        $last = $this->last();

        if ($last === []) {
            return [];
        }

        $summary = [
            'Mode' => ($last['mode'] ?? '') === 'update' ? 'Sync Status Updates' : 'Load New Orders',
            'Recorded at' => $last['recorded_at'] ?? '—',
            'Fetched from OC' => (string) ($last['fetched'] ?? 0),
            'Requested status_ids' => isset($last['requested_status_ids']) && is_array($last['requested_status_ids'])
                ? '['.implode(', ', array_map('intval', $last['requested_status_ids'])).']'
                : '—',
            'Connector raw count' => (string) ($last['connector_raw_count'] ?? $last['fetched'] ?? 0),
        ];

        if (($last['mode'] ?? '') === 'import') {
            $summary['Imported'] = (string) ($last['imported'] ?? 0);
            $summary['Duplicates skipped'] = (string) ($last['duplicates_skipped'] ?? 0);
            $summary['Update-only skipped'] = (string) ($last['update_only_skipped'] ?? 0);
            $summary['Unmatched product lines'] = (string) ($last['unmatched_lines'] ?? 0);
        } else {
            $summary['Updated'] = (string) ($last['updated'] ?? 0);
            $summary['Not found skipped'] = (string) ($last['not_found_skipped'] ?? 0);
            $summary['Locked skipped'] = (string) ($last['locked_skipped'] ?? 0);
        }

        $skipLog = $last['skip_log'] ?? [];
        if (is_array($skipLog) && $skipLog !== []) {
            $summary['Skipped orders'] = count($skipLog);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public function formatSkipRow(array $entry): string
    {
        $orderId = (string) ($entry['order_id'] ?? '—');
        $ocLabel = $this->formatOcStatusLabel(
            (int) ($entry['oc_status_id'] ?? 0),
            (string) ($entry['oc_status_name'] ?? '')
        );
        $reason = $this->formatSkipReason(
            (string) ($entry['reason'] ?? ''),
            (string) ($entry['detail'] ?? '')
        );

        return sprintf('#%s | %s | %s', $orderId, $ocLabel, $reason);
    }
}
