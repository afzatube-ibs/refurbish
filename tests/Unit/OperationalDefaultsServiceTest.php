<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Supplier;
use App\Services\OperationalDefaultsService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDefaultsServiceTest extends TestCase
{
    use RefreshDatabase;

    private OperationalDefaultsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        Connection::getInstance()->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);

        $this->service = app(OperationalDefaultsService::class);
    }

    public function test_resolves_default_supplier_from_connection_filter(): void
    {
        $supplier = $this->service->defaultSupplier();

        $this->assertSame('Ex-A', $supplier->name);
        $this->assertSame($supplier->id, $this->service->defaultSupplierId());
    }

    public function test_resolves_default_active_connection(): void
    {
        $connection = $this->service->defaultConnection();

        $this->assertTrue($connection->is_active);
        $this->assertSame($connection->id, $this->service->defaultConnectionId());
    }

    public function test_detects_single_supplier_and_store(): void
    {
        $this->assertTrue($this->service->hasSingleSupplier());
        $this->assertTrue($this->service->hasSingleStore());
    }

    public function test_manual_order_defaults_use_lokkisona_manual(): void
    {
        $defaults = $this->service->manualOrderDefaults();

        $this->assertSame('lokkisona', $defaults['source_store']);
        $this->assertSame('phone', $defaults['source_type']);
        $this->assertSame('Lokkisona Manual', $defaults['source_label']);
    }

    public function test_store_label_uses_connection_host(): void
    {
        $this->assertSame('www.staging.lokkisona.com', $this->service->storeLabel());
    }
}
