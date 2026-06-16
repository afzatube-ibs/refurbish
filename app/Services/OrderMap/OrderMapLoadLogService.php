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
        return sprintf(
            'Load complete: %d fetched, %d imported, %d duplicates skipped.',
            (int) ($result['fetched'] ?? 0),
            (int) ($result['imported'] ?? 0),
            (int) ($result['duplicates_skipped'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function formatUpdateBanner(array $result): string
    {
        return sprintf(
            'Sync complete: %d fetched, %d updated, %d not found, %d locked.',
            (int) ($result['fetched'] ?? 0),
            (int) ($result['updated'] ?? 0),
            (int) ($result['not_found_skipped'] ?? 0),
            (int) ($result['locked_skipped'] ?? 0),
        );
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
            'Fetched' => (string) ($last['fetched'] ?? 0),
        ];

        if (($last['mode'] ?? '') === 'import') {
            $summary['Imported'] = (string) ($last['imported'] ?? 0);
            $summary['Duplicates skipped'] = (string) ($last['duplicates_skipped'] ?? 0);
            $summary['Unmatched lines'] = (string) ($last['unmatched_lines'] ?? 0);
        } else {
            $summary['Updated'] = (string) ($last['updated'] ?? 0);
            $summary['Not found skipped'] = (string) ($last['not_found_skipped'] ?? 0);
            $summary['Locked skipped'] = (string) ($last['locked_skipped'] ?? 0);
        }

        $skipLog = $last['skip_log'] ?? [];
        if (is_array($skipLog) && $skipLog !== []) {
            $summary['Skip entries'] = count($skipLog);
        }

        return $summary;
    }
}
