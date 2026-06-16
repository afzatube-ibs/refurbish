<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\OpenCartMediaUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductMapPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dropflow.modules.product_map' => true,
            'dropflow.oc_mock' => true,
            'dropflow.live_read_only' => false,
            'dropflow.product_preview_target' => 42,
            'dropflow.product_preview_page_size' => 20,
        ]);
    }

    protected function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-preview@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
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

    public function test_preview_page_loads_without_data(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('Product Mapping Center')
            ->assertSee('Load Products')
            ->assertSee('No products loaded')
            ->assertDontSee('Parent View')
            ->assertDontSee('Variant View');
    }

    public function test_load_preview_fetches_all_pages_until_has_next_false(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success');

        $preview = session('product_preview');

        $this->assertIsArray($preview);
        $this->assertTrue($preview['meta']['read_only'] ?? false);
        $this->assertCount(42, $preview['products'] ?? []);
        $this->assertSame(3, (int) ($preview['meta']['pages_fetched'] ?? 0));
        $this->assertFalse($preview['meta']['has_next'] ?? true);
        $this->assertGreaterThanOrEqual(1, (int) ($preview['meta']['duplicates_skipped'] ?? 0));
        $this->assertSame(0, (int) ($preview['summary']['duplicate_parents'] ?? -1));
    }

    public function test_first_product_with_options_is_variable(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'));

        $preview = session('product_preview');
        $first = $preview['products'][0] ?? null;

        $this->assertNotNull($first);
        $this->assertSame('E-601-GREEN', $first['model'] ?? '');
        $this->assertSame('IBS-E601', $first['ibs_model'] ?? '');
        $this->assertSame('variable', $first['type'] ?? '');
        $this->assertNull($first['rate'] ?? 'unset');
        $this->assertSame(5, $first['low_warning'] ?? null);
        $this->assertNull($first['ibs_stock'] ?? 'unset');
        $this->assertSame('', $first['sm_model'] ?? 'unset');
        $this->assertCount(5, $first['options'] ?? []);
        $this->assertNotEmpty($first['options'][0]['image'] ?? null);
        $this->assertSame('E-601-GREEN-1', $first['options'][0]['model'] ?? '');
        $this->assertNull($first['options'][0]['rate'] ?? 'unset');
        $this->assertNull($first['options'][0]['low_warning'] ?? 'unset');
    }

    public function test_summary_counts_option_images_and_models_by_row(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'));

        $summary = session('product_preview.summary');

        $this->assertSame(5, (int) ($summary['option_images_count'] ?? 0));
        $this->assertSame(5, (int) ($summary['variant_models_count'] ?? 0));
        $this->assertSame(5, (int) ($summary['variant_rows'] ?? 0));
    }

    public function test_preview_persists_after_navigation(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'));

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('E-601-GREEN')
            ->assertSee('Products');
    }

    public function test_refresh_preview_reloads_products(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)->post(route('product-map.load'));

        $this->actingAs($user)
            ->post(route('product-map.refresh'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success');

        $preview = session('product_preview');

        $this->assertCount(42, $preview['products'] ?? []);
        $this->assertNotEmpty($preview['meta']['loaded_at'] ?? null);
    }

    public function test_preview_listing_shows_required_columns_and_variable_button(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'));

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('Load Products')
            ->assertSee('Refresh Preview')
            ->assertSee('Product ID')
            ->assertSee('Main Image')
            ->assertSee('IBS Model')
            ->assertSee('SM Model')
            ->assertSee('IBS Stock')
            ->assertSee('Total Products')
            ->assertSee('Ready')
            ->assertSee('Needs Work')
            ->assertSee('Search & Filter')
            ->assertSee('Page size')
            ->assertSee('Product Type')
            ->assertSee('Category')
            ->assertSee('History')
            ->assertSee('Page 1 of 3 · 42 records')
            ->assertSee('Previous')
            ->assertSee('Next')
            ->assertSee('Variable (5)')
            ->assertDontSee('Advanced Diagnostics')
            ->assertDontSee('Option Name')
            ->assertDontSee('Option Value');
    }

    public function test_preview_listing_pagination_page_two_shows_next_slice(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'));

        $preview = session('product_preview');
        $pageOneIds = array_slice(array_column($preview['products'] ?? [], 'model'), 0, 20);
        $pageTwoIds = array_slice(array_column($preview['products'] ?? [], 'model'), 20, 20);

        $this->actingAs($user)
            ->get(route('product-map.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 3 · 42 records')
            ->assertSee($pageTwoIds[0] ?? 'missing-page-two-model')
            ->assertDontSee($pageOneIds[0] ?? 'missing-page-one-model');
    }

    public function test_parent_health_rolls_up_variant_negative_stock_but_not_missing_option_image(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends \App\Services\OpenCart\ProductPreviewService
        {
            public function normalizeForTest(array $product): array
            {
                $normalized = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                return $this->applyHealthRules([$normalized])[0];
            }
        };

        $negativeVariant = $service->normalizeForTest([
            'model' => 'PARENT-1',
            'ibs_model' => 'IBS-PARENT-1',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => 10.0,
            'options' => [
                ['model' => 'OPT-1', 'quantity' => -1, 'image' => 'catalog/opt.jpg', 'ibs_model' => 'IBS-OPT-1', 'ibs_stock' => 5],
            ],
        ]);

        $this->assertSame('needs_attention', $negativeVariant['health']['status']);
        $this->assertContains('Negative stock', $negativeVariant['health']['issues']);

        $missingOptionImage = $service->normalizeForTest([
            'model' => 'PARENT-2',
            'ibs_model' => 'IBS-PARENT-2',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => 10.0,
            'options' => [
                ['model' => 'OPT-2', 'quantity' => 10, 'image' => null, 'ibs_model' => 'IBS-OPT-2', 'ibs_stock' => 10],
            ],
        ]);

        $this->assertSame('needs_attention', $missingOptionImage['health']['status']);
        $this->assertSame('needs_attention', $missingOptionImage['options'][0]['health']['status']);
        $this->assertContains('Missing option image', $missingOptionImage['options'][0]['health']['issues']);
    }

    public function test_low_warning_marks_variant_alert_when_ibs_stock_below_threshold(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends \App\Services\OpenCart\ProductPreviewService
        {
            public function normalizeForTest(array $product): array
            {
                $normalized = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                return $this->applyHealthRules([$normalized])[0];
            }
        };

        $product = $service->normalizeForTest([
            'model' => 'PARENT-LOW',
            'ibs_model' => 'IBS-PARENT-LOW',
            'image' => 'catalog/p.jpg',
            'stock' => 20,
            'rate' => 99.0,
            'options' => [
                [
                    'model' => 'OPT-LOW',
                    'quantity' => 20,
                    'image' => 'catalog/opt.jpg',
                    'ibs_model' => 'IBS-OPT-LOW',
                    'ibs_stock' => 3,
                ],
            ],
        ]);

        $this->assertSame('alert', $product['health']['status']);
        $this->assertContains('Low stock', $product['health']['issues']);
        $this->assertSame('alert', $product['options'][0]['health']['status']);
    }

    public function test_duplicate_ibs_model_marks_review(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends \App\Services\OpenCart\ProductPreviewService
        {
            public function applyForTest(array $products): array
            {
                $normalized = array_map(
                    fn (array $product) => $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com')),
                    $products
                );

                return $this->applyHealthRules($normalized);
            }
        };

        $products = $service->applyForTest([
            [
                'model' => 'PARENT-A',
                'ibs_model' => 'IBS-DUP',
                'image' => 'catalog/a.jpg',
                'stock' => 10,
                'rate' => 10.0,
                'ibs_stock' => 8,
                'options' => [],
            ],
            [
                'model' => 'PARENT-B',
                'ibs_model' => 'IBS-DUP',
                'image' => 'catalog/b.jpg',
                'stock' => 10,
                'rate' => 10.0,
                'ibs_stock' => 8,
                'options' => [],
            ],
        ]);

        $this->assertSame('needs_attention', $products[0]['health']['status']);
        $this->assertContains('Duplicate IBS model', $products[0]['health']['issues']);
        $this->assertSame('needs_attention', $products[1]['health']['status']);
    }

    public function test_negative_stock_marks_parent_health_review(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends \App\Services\OpenCart\ProductPreviewService
        {
            public function healthFor(array $product): array
            {
                $normalized = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                return $this->applyHealthRules([$normalized])[0]['health'];
            }
        };

        $health = $service->healthFor([
            'model' => 'NEG-001',
            'ibs_model' => 'IBS-NEG-001',
            'image' => 'catalog/p.jpg',
            'stock' => -3,
            'rate' => 10.0,
            'ibs_stock' => 5,
            'options' => [],
        ]);

        $this->assertSame('needs_attention', $health['status']);
        $this->assertContains('Negative stock', $health['issues']);
    }

    public function test_relative_image_url_is_resolved_against_store(): void
    {
        $resolver = new OpenCartMediaUrlResolver();
        $context = OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com');

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/toys/560.jpg',
            $resolver->resolveProductImage(['image' => 'catalog/Products/toys/560.jpg'], $context)
        );

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/my%20toy/560.jpg',
            $resolver->resolveOptionImage(['image' => 'catalog/Products/my toy/560.jpg'], $context)
        );
    }

    public function test_load_preview_resolves_product_and_option_image_urls(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'));

        $first = session('product_preview.products.0');
        $meta = session('product_preview.meta');

        $this->assertSame('https://www.staging.lokkisona.com', $meta['image_resolve_base'] ?? '');
        $this->assertNotSame('https://store.example.com', $meta['image_resolve_base'] ?? '');
        $this->assertStringStartsWith('https://www.staging.lokkisona.com/image/catalog/Products/toys/', $first['image'] ?? '');
        $this->assertStringStartsWith('https://www.staging.lokkisona.com/image/catalog/Products/toys/', $first['options'][0]['image'] ?? '');
    }

    public function test_load_blocked_without_active_connection(): void
    {
        Connection::getInstance()->update(['is_active' => false, 'store_url' => '']);

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('error');
    }
}

