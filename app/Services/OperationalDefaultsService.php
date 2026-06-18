<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Supplier;
use Illuminate\Http\Request;
use RuntimeException;

class OperationalDefaultsService
{
    public function defaultConnection(): Connection
    {
        return Connection::query()
            ->where('is_active', true)
            ->first()
            ?? Connection::getInstance();
    }

    public function defaultSupplier(?Connection $connection = null): Supplier
    {
        $connection ??= $this->defaultConnection();

        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($connection) {
                $query->where('code', $connection->supplier_filter)
                    ->orWhere('code', strtoupper((string) $connection->supplier_filter));
            })
            ->first();

        if ($supplier) {
            return $supplier;
        }

        $fallback = Supplier::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new RuntimeException('No active supplier configured.');
        }

        return $fallback;
    }

    public function defaultSupplierId(): int
    {
        return (int) $this->defaultSupplier()->id;
    }

    public function defaultConnectionId(): int
    {
        return (int) $this->defaultConnection()->id;
    }

    public function hasSingleSupplier(): bool
    {
        return Supplier::query()->where('is_active', true)->count() === 1;
    }

    public function hasSingleStore(): bool
    {
        return Connection::query()->where('is_active', true)->count() <= 1;
    }

    /**
     * @return array{source_store: string, source_type: string, source_label: string}
     */
    public function manualOrderDefaults(): array
    {
        return [
            'source_store' => 'lokkisona',
            'source_type' => 'phone',
            'source_label' => 'Lokkisona Manual',
        ];
    }

    /**
     * @return array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null, courier?: string|null, search?: string|null, user_supplier_id?: int|null}
     */
    public function applyReportFilters(Request $request): array
    {
        $user = $request->user();
        $filters = [];

        if ($user->isSupplier()) {
            $filters['supplier_id'] = $user->supplier_id;
            $filters['user_supplier_id'] = $user->supplier_id;
        } elseif ($request->has('supplier_id') && $request->query('supplier_id') !== '') {
            $filters['supplier_id'] = (int) $request->query('supplier_id');
        } else {
            $filters['supplier_id'] = $this->defaultSupplierId();
        }

        if ($request->has('connection_id') && $request->query('connection_id') !== '') {
            $filters['connection_id'] = (int) $request->query('connection_id');
        } else {
            $filters['connection_id'] = $this->defaultConnectionId();
        }

        if ($from = $request->query('from')) {
            $filters['from'] = $from;
        }

        if ($to = $request->query('to')) {
            $filters['to'] = $to;
        }

        if ($courier = $request->query('courier')) {
            $filters['courier'] = $courier;
        }

        if ($search = $request->query('search')) {
            $filters['search'] = $search;
        }

        return $filters;
    }

    public function storeLabel(?Connection $connection = null): string
    {
        $connection ??= $this->defaultConnection();

        if (! filled($connection->store_url)) {
            return 'Store #'.$connection->id;
        }

        $host = parse_url($connection->store_url, PHP_URL_HOST);

        return $host ?: $connection->store_url;
    }
}
