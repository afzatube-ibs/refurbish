<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ProductMap\ProductMapProduct;
use App\Models\User;
use App\Services\ProductMap\ProductMapCatalogService;
use App\Services\ProductMap\ProductMapLogsService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\Concerns\SeedsProductMapCatalog;
use Tests\TestCase;

class ProductMapLogsTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;
    use SeedsProductMapCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dropflow.modules.product_map' => true,
            'dropflow.oc_mock' => true,
            'dropflow.product_preview_target' => 42,
            'dropflow.product_preview_page_size' => 20,
        ]);

        $this->seed(SupplierSeeder::class);
    }

    protected function activeConnection(): void
    {
        Connection::getInstance()->update([
            'store_url' => 'https://store.example.com',
            'api_token' => 'preview-token',
            'product_api_endpoint' => 'index.php?route=api/ibs/products',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);
    }

    protected function loadAndConfirm(?User $user = null): void
    {
        $user = $user ?? $this->adminUser();

        $this->actingAs($user)->post(route('product-map.load'));
        $this->actingAs($user)->post(route('product-map.load.confirm'));
    }

    public function test_clear_logs_keeps_database_catalog_rows(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);
        $this->assertSame(42, ProductMapProduct::query()->count());

        session()->put(\App\Http\Controllers\ProductMapController::SYNC_CONTEXT_SESSION_KEY, [
            'diagnostics' => ['raw_product_count' => 42],
            'meta' => ['pages_fetched' => 3],
        ]);

        $this->actingAs($user)
            ->from(route('product-map.index'))
            ->post(route('product-map.clear-logs'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('logs_tab', 'product-map');

        $this->assertSame(42, ProductMapProduct::query()->count());
        $this->assertNull(session(ProductMapLogsService::SESSION_KEY));
    }

    public function test_reset_product_map_clears_session_but_not_db_catalog_or_control_state(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();
        $this->seedProductMapCatalog();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 88.5],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->from(route('product-map.index'))
            ->post(route('product-map.reset'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('logs_tab', 'product-map');

        $this->assertNull(session('product_map_pending_load'));
        $this->assertSame(1, ProductMapProduct::query()->count());
        $this->assertDatabaseHas('product_control_states', [
            'source_product_id' => '9509',
            'rate' => 88.5,
        ]);
        $this->assertDatabaseHas('product_rate_history', [
            'product_id' => '9509',
            'new_rate' => 88.5,
        ]);

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('PARENT-9509');
    }

    public function test_product_map_logs_panel_offers_reset_when_products_loaded(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('Reset Product Map')
            ->assertSee('Clear logs');
    }
}
