<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\OpenCartMediaUrlResolver;
use App\Services\OpenCart\ProductPreviewService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class ProductMapPreviewTest extends TestCase
{
    use CreatesUniqueAdminUser;
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

        $this->seed(SupplierSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function productWithHealthRules(array $product): array
    {
        $service = $this->healthTestService();

        return $service->applyFixture($product);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function productsWithHealthRules(array $products): array
    {
        $service = $this->healthTestService();

        return $service->applyFixtures($products);
    }

    protected function healthTestService(): object
    {
        return new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            /**
             * @param  array<string, mixed>  $product
             * @return array<string, mixed>
             */
            public function applyFixture(array $product): array
            {
                $normalized = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                foreach (['rate', 'ibs_stock', 'low_warning', 'sm_model'] as $field) {
                    if (array_key_exists($field, $product)) {
                        $normalized[$field] = $product[$field];
                    }
                }

                if (isset($product['options']) && is_array($product['options'])) {
                    foreach ($product['options'] as $index => $fixtureOption) {
                        if (! is_array($fixtureOption) || ! is_array($normalized['options'][$index] ?? null)) {
                            continue;
                        }

                        foreach (['rate', 'ibs_stock', 'low_warning', 'quantity', 'stock', 'image'] as $field) {
                            if (array_key_exists($field, $fixtureOption)) {
                                $normalized['options'][$index][$field] = $fixtureOption[$field];

                                if ($field === 'quantity') {
                                    $normalized['options'][$index]['stock'] = (int) $fixtureOption[$field];
                                }
                            }
                        }
                    }
                }

                return $this->applyHealthRules([$normalized])[0];
            }

            /**
             * @param  array<int, array<string, mixed>>  $products
             * @return array<int, array<string, mixed>>
             */
            public function applyFixtures(array $products): array
            {
                $normalized = [];

                foreach ($products as $product) {
                    $row = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                    foreach (['rate', 'ibs_stock', 'low_warning', 'sm_model'] as $field) {
                        if (array_key_exists($field, $product)) {
                            $row[$field] = $product[$field];
                        }
                    }

                    $normalized[] = $row;
                }

                return $this->applyHealthRules($normalized);
            }
        };
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

    protected function loadAndConfirm(?User $user = null): void
    {
        $user = $user ?? $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'));

        $this->actingAs($user)
            ->post(route('product-map.load.confirm'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success');
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
        $user = $this->adminUser('product-map-preview');

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('info');

        $this->assertNull(session('product_preview'));

        $this->actingAs($user)
            ->post(route('product-map.load.confirm'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success', '42 products added to Product Map.');

        $preview = session('product_preview');

        $this->assertIsArray($preview);
        $this->assertTrue($preview['meta']['read_only'] ?? false);
        $this->assertCount(42, $preview['products'] ?? []);
        $this->assertSame(3, (int) ($preview['meta']['pages_fetched'] ?? 0));
        $this->assertFalse($preview['meta']['has_next'] ?? true);
        $this->assertGreaterThanOrEqual(1, (int) ($preview['meta']['duplicates_skipped'] ?? 0));
        $this->assertSame(0, (int) ($preview['summary']['duplicate_parents'] ?? -1));
    }

    public function test_load_without_confirm_does_not_populate_preview(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('info', '42 new products found');

        $this->assertNull(session('product_preview'));
        $this->assertSame(42, (int) (session('product_map_pending_load.count') ?? 0));
    }

    public function test_load_cancel_clears_pending_without_adding_products(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)->post(route('product-map.load'));

        $this->actingAs($user)
            ->post(route('product-map.load.cancel'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('info');

        $this->assertNull(session('product_preview'));
        $this->assertNull(session('product_map_pending_load'));
    }

    public function test_refresh_without_loaded_products_shows_error(): void
    {
        $this->activeConnection();

        $this->actingAs($this->adminUser())
            ->post(route('product-map.refresh'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('error', 'No products loaded yet. Please use Load Products first.');

        $this->assertNull(session('product_preview'));
    }

    public function test_refresh_does_not_expand_product_list(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $preview = session('product_preview');
        $preview['products'] = array_slice($preview['products'], 0, 3);
        session(['product_preview' => $preview]);

        $this->actingAs($user)
            ->post(route('product-map.refresh'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('success');

        $this->assertCount(3, session('product_preview.products') ?? []);
    }

    public function test_load_detects_only_new_products_when_list_partially_loaded(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $preview = session('product_preview');
        $preview['products'] = [($preview['products'][0] ?? [])];
        session(['product_preview' => $preview]);

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('info', '41 new products found');

        $this->assertCount(1, session('product_preview.products') ?? []);
        $this->assertSame(41, (int) (session('product_map_pending_load.count') ?? 0));
    }

    public function test_first_product_with_options_is_variable(): void
    {
        $this->activeConnection();

        $this->loadAndConfirm();

        $preview = session('product_preview');
        $first = $preview['products'][0] ?? null;

        $this->assertNotNull($first);
        $this->assertSame('E-601-GREEN', $first['model'] ?? '');
        $this->assertSame('IBS-E601', $first['ibs_model'] ?? '');
        $this->assertSame('variable', $first['type'] ?? '');
        $this->assertTrue(! array_key_exists('rate', $first) || $first['rate'] === null);
        $this->assertSame(5, $first['low_warning'] ?? null);
        $this->assertTrue(! array_key_exists('ibs_stock', $first) || $first['ibs_stock'] === null);
        $this->assertSame('', $first['sm_model'] ?? '');
        $this->assertCount(5, $first['options'] ?? []);
        $this->assertNotEmpty($first['options'][0]['image'] ?? null);
        $this->assertSame('E-601-GREEN-1', $first['options'][0]['model'] ?? '');
        $this->assertTrue(! array_key_exists('rate', $first['options'][0]) || $first['options'][0]['rate'] === null);
        $this->assertTrue(! array_key_exists('low_warning', $first['options'][0]) || $first['options'][0]['low_warning'] === null);
    }

    public function test_summary_counts_option_images_and_models_by_row(): void
    {
        $this->activeConnection();

        $this->loadAndConfirm();

        $summary = session('product_preview.summary');

        $this->assertSame(5, (int) ($summary['option_images_count'] ?? 0));
        $this->assertSame(5, (int) ($summary['variant_models_count'] ?? 0));
        $this->assertSame(5, (int) ($summary['variant_rows'] ?? 0));
    }

    public function test_preview_persists_after_navigation(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

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

        $this->loadAndConfirm($user);

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

        $this->loadAndConfirm($user);

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

        $this->loadAndConfirm($user);

        $preview = session('product_preview');
        $pageTwoIds = array_slice(array_column($preview['products'] ?? [], 'model'), 20, 20);

        $this->actingAs($user)
            ->get(route('product-map.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 3 · 42 records')
            ->assertSee($pageTwoIds[0] ?? 'missing-page-two-model');
    }

    public function test_parent_health_rolls_up_variant_negative_stock_but_not_missing_option_image(): void
    {
        $negativeVariant = $this->productWithHealthRules([
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

        $missingOptionImage = $this->productWithHealthRules([
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
        $product = $this->productWithHealthRules([
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
        $products = $this->productsWithHealthRules([
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
        $product = $this->productWithHealthRules([
            'model' => 'NEG-001',
            'ibs_model' => 'IBS-NEG-001',
            'image' => 'catalog/p.jpg',
            'stock' => -3,
            'rate' => 10.0,
            'ibs_stock' => 5,
            'options' => [],
        ]);

        $this->assertSame('needs_attention', $product['health']['status']);
        $this->assertContains('Negative stock', $product['health']['issues']);
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

        $this->loadAndConfirm();

        $first = session('product_preview.products.0');
        $meta = session('product_preview.meta');

        $this->assertSame('https://www.staging.lokkisona.com', $meta['image_resolve_base'] ?? '');
        $this->assertNotSame('https://store.example.com', $meta['image_resolve_base'] ?? '');
        $this->assertStringStartsWith('https://www.staging.lokkisona.com/image/catalog/Products/toys/', $first['image'] ?? '');
        $this->assertStringStartsWith('https://www.staging.lokkisona.com/image/catalog/Products/toys/', $first['options'][0]['image'] ?? '');
    }

    public function test_load_when_all_products_already_loaded_shows_no_new_message(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'))
            ->assertSessionHas('info', 'No new products found.');

        $this->assertNull(session('product_map_pending_load'));
        $this->assertCount(42, session('product_preview.products') ?? []);
    }

    public function test_pending_load_review_shows_preview_table_and_actions(): void
    {
        $this->activeConnection();
        $user = $this->adminUser('product-map-preview');

        $this->actingAs($user)
            ->post(route('product-map.load'));

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('Review before adding')
            ->assertSee('42 products fetched')
            ->assertSee('OC Product ID')
            ->assertSee('OC Model')
            ->assertSee('Type')
            ->assertSee('Status')
            ->assertSee('New')
            ->assertSee('E-601-GREEN')
            ->assertSee('Variable (5)')
            ->assertSee('Add All New')
            ->assertSee('Cancel')
            ->assertDontSee('No products loaded');
    }

    public function test_pending_load_incremental_review_shows_only_new_count_message(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->loadAndConfirm($user);

        $preview = session('product_preview');
        $preview['products'] = [($preview['products'][0] ?? [])];
        session(['product_preview' => $preview]);

        $this->actingAs($user)->post(route('product-map.load'));

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('41 new products found')
            ->assertSee('Review before adding')
            ->assertSee('Add All New')
            ->assertDontSee('No products loaded');
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

