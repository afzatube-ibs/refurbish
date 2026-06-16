<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductMapLogsService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class ProductMapLogsTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

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

    protected function seedPreviewSession(): void
    {
        $anonymous = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function buildSample(): array
            {
                $product = $this->normalizeProduct([
                    'product_id' => '9509',
                    'model' => 'PARENT-9509',
                    'ibs_model' => 'IBS-9509',
                    'image' => 'catalog/p.jpg',
                    'stock' => 12,
                    'from_warehouse' => 1,
                    'options' => [],
                ], OpenCartImageContext::fromStoreUrl('https://example.com'));

                $products = $this->applyHealthRules([$product]);

                return [
                    'products' => $products,
                    'activity' => [],
                    'meta' => ['has_local_edits' => false, 'loaded_at' => now()->toIso8601String()],
                    'summary' => $this->buildSummary([[]], $products),
                    'diagnostics' => ['raw_product_count' => 1, 'pagination' => ['page' => 1]],
                ];
            }
        };

        session(['product_preview' => $anonymous->buildSample()]);
    }

    protected function loadAndConfirm(?User $user = null): void
    {
        $user = $user ?? $this->adminUser();

        $this->actingAs($user)->post(route('product-map.load'));
        $this->actingAs($user)->post(route('product-map.load.confirm'));
    }

    public function test_clear_logs_keeps_product_preview_rows(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $preview = session('product_preview');
        $this->assertNotEmpty($preview['products'] ?? []);
        $this->assertNotEmpty($preview['diagnostics'] ?? []);

        $this->actingAs($user)
            ->from(route('product-map.index'))
            ->post(route('product-map.clear-logs'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('logs_tab', 'product-map');

        $after = session('product_preview');

        $this->assertCount(42, $after['products'] ?? []);
        $this->assertSame([], $after['diagnostics'] ?? null);
        $this->assertSame([], $after['activity'] ?? null);
        $this->assertNull(session(ProductMapLogsService::SESSION_KEY));
    }

    public function test_reset_product_map_clears_session_products_but_not_db_control_state(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();
        $this->seedPreviewSession();

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

        $this->assertNull(session('product_preview'));
        $this->assertNull(session('product_map_pending_load'));
        $this->assertDatabaseHas('product_control_states', [
            'source_product_id' => '9509',
            'rate' => 88.5,
        ]);
        $this->assertDatabaseHas('product_rate_history', [
            'product_id' => '9509',
            'new_rate' => 88.5,
        ]);
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
