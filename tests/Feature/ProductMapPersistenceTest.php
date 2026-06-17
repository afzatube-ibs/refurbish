<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductMapProduct;
use App\Services\OpenCart\OpenCartHttpClient;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductMapCatalogService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\Concerns\SeedsProductMapCatalog;
use Tests\TestCase;

class ProductMapPersistenceTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;
    use SeedsProductMapCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dropflow.modules.product_map' => true,
            'dropflow.version' => 'v0.6.2',
            'dropflow.oc_mock' => true,
            'dropflow.live_read_only' => false,
            'dropflow.product_preview_target' => 42,
            'dropflow.product_preview_page_size' => 20,
        ]);

        $this->seed(SupplierSeeder::class);
        $this->activeConnection();
    }

    protected function activeConnection(): Connection
    {
        $connection = Connection::getInstance();
        $connection->update([
            'store_url' => 'https://store.example.com',
            'api_token' => 'preview-token',
            'product_api_endpoint' => 'index.php?route=api/ibs/products',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);

        return $connection->fresh();
    }

    public function test_index_loads_from_database_without_connector_call(): void
    {
        $this->seedProductMapCatalog();

        $mockClient = $this->mock(OpenCartHttpClient::class, function ($mock) {
            $mock->shouldNotReceive('get');
        });
        $this->app->instance(OpenCartHttpClient::class, $mockClient);

        $this->actingAs($this->adminUser())
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('PARENT-9509')
            ->assertSee('Refresh Local List')
            ->assertSee('v0.6.2');
    }

    public function test_refresh_local_list_does_not_call_opencart(): void
    {
        $this->seedProductMapCatalog();
        $user = $this->adminUser();

        $mockClient = $this->mock(OpenCartHttpClient::class, function ($mock) {
            $mock->shouldNotReceive('get');
        });
        $this->app->instance(OpenCartHttpClient::class, $mockClient);

        $this->actingAs($user)
            ->post(route('product-map.refresh'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success', 'Local product list refreshed.');

        $this->assertSame(1, ProductMapProduct::query()->count());
    }

    public function test_sync_oc_products_calls_opencart_only_on_button_click(): void
    {
        $user = $this->adminUser();
        $realClient = new OpenCartHttpClient(Connection::getInstance());
        $called = false;

        $mockClient = $this->mock(OpenCartHttpClient::class, function ($mock) use ($realClient, &$called) {
            $mock->shouldReceive('get')->andReturnUsing(function (string $endpoint, array $params = [], ?int $timeout = null) use ($realClient, &$called) {
                $called = true;

                return $realClient->get($endpoint, $params, $timeout);
            });
        });
        $this->app->instance(OpenCartHttpClient::class, $mockClient);

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk();

        $this->assertFalse($called);

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'));

        $this->assertTrue($called);
        $this->assertSame(0, ProductMapProduct::query()->count());
        $this->assertIsArray(session('product_map_pending_load'));
    }

    public function test_existing_ibs_fields_survive_oc_sync(): void
    {
        $this->seedProductMapCatalog();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 77.25],
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 19],
                ],
            ])
            ->assertOk();

        $this->loadAndConfirmCatalog($user);

        $state = ProductControlState::query()->where('source_product_id', '9509')->first();
        $this->assertNotNull($state);
        $this->assertSame('77.25', (string) $state->rate);

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk();

        $preview = app(ProductMapCatalogService::class)->buildPreview();
        $product = collect($preview['products'] ?? [])->first(
            fn (array $row) => (string) ($row['product_id'] ?? '') === '9509'
        );

        $this->assertNotNull($product);
        $this->assertSame(77.25, (float) ($product['rate'] ?? 0));
    }

    public function test_seeded_database_products_render_immediately_without_sync(): void
    {
        $this->seedProductMapCatalog();

        $this->actingAs($this->adminUser())
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('PARENT-9509')
            ->assertSee('Total Products')
            ->assertDontSee('No products loaded');
    }

    public function test_version_appears_in_ui_and_config(): void
    {
        $this->assertSame('v0.6.2', config('dropflow.version'));

        $this->actingAs($this->adminUser())
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('v0.6.2');
    }

    public function test_confirm_sync_persists_products_to_database(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)->post(route('product-map.load'));
        $this->assertSame(0, ProductMapProduct::query()->count());

        $this->actingAs($user)->post(route('product-map.load.confirm'));

        $this->assertSame(42, ProductMapProduct::query()->count());
        $this->assertDatabaseHas('product_map_products', ['source_product_id' => '100']);
    }
}
