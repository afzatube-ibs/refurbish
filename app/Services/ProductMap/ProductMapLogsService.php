<?php

namespace App\Services\ProductMap;

class ProductMapLogsService
{
    public const SESSION_KEY = 'product_map_logs';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $logs = session(self::SESSION_KEY);

        return is_array($logs) ? $logs : [];
    }

    public function hasClearableLogs(): bool
    {
        $preview = session('product_preview');

        if (is_array($preview)) {
            if ($this->hasContent($preview['diagnostics'] ?? null)) {
                return true;
            }

            if ($this->hasContent($preview['activity'] ?? null)) {
                return true;
            }
        }

        return $this->hasContent($this->all());
    }

    public function lastError(): ?string
    {
        $logs = $this->all();
        $stored = $logs['last_error'] ?? null;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $sessionError = session('error');

        if (! is_string($sessionError) || $sessionError === '') {
            return null;
        }

        $needle = strtolower($sessionError);

        if (
            str_contains($needle, 'preview')
            || str_contains($needle, 'refresh')
            || str_contains($needle, 'load')
            || str_contains($needle, 'product map')
            || str_contains($needle, 'opencart')
        ) {
            return $sessionError;
        }

        return null;
    }

    public function recordError(string $message): void
    {
        session()->put(self::SESSION_KEY, array_merge($this->all(), [
            'last_error' => $message,
            'recorded_at' => now()->toIso8601String(),
        ]));
    }

    public function recordLoadEvent(string $action, array $context = []): void
    {
        $logs = $this->all();
        $entries = is_array($logs['entries'] ?? null) ? $logs['entries'] : [];
        $entries[] = array_merge([
            'action' => $action,
            'at' => now()->toIso8601String(),
        ], $context);

        session()->put(self::SESSION_KEY, array_merge($logs, [
            'last_action' => $action,
            'last_action_at' => now()->toIso8601String(),
            'entries' => array_slice($entries, -25),
        ]));
    }

    public function clear(): void
    {
        $preview = session('product_preview');

        if (is_array($preview)) {
            $preview['diagnostics'] = [];
            $preview['activity'] = [];
            session()->put('product_preview', $preview);
        }

        session()->forget(self::SESSION_KEY);
    }

    public function resetProductMapSession(): void
    {
        session()->forget([
            'product_preview',
            'product_map_pending_load',
            self::SESSION_KEY,
        ]);
    }

    protected function hasContent(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        return $value !== [];
    }
}
