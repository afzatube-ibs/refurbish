<?php

namespace App\Services\ProductMap;

use App\Http\Controllers\ProductMapController;

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
        $syncContext = session(ProductMapController::SYNC_CONTEXT_SESSION_KEY);

        if (is_array($syncContext) && $this->hasContent($syncContext['diagnostics'] ?? null)) {
            return true;
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
            || str_contains($needle, 'sync')
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
        $syncContext = session(ProductMapController::SYNC_CONTEXT_SESSION_KEY);

        if (is_array($syncContext)) {
            $syncContext['diagnostics'] = [];
            session()->put(ProductMapController::SYNC_CONTEXT_SESSION_KEY, $syncContext);
        }

        session()->forget(self::SESSION_KEY);
    }

    public function resetProductMapSession(): void
    {
        session()->forget([
            'product_preview',
            'product_map_pending_load',
            ProductMapController::SYNC_CONTEXT_SESSION_KEY,
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
