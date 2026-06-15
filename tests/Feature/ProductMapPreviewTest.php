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
            ->assertSee('Load Products')
            ->assertSee('No preview loaded');
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
        $this->assertSame('E-601-GREEN', $first['ibs_model'] ?? '');
        $this->assertSame('variable', $first['type'] ?? '');
        $this->assertCount(5, $first['options'] ?? []);
        $this->assertNotEmpty($first['options'][0]['image'] ?? null);
        $this->assertSame('E-601-GREEN-1', $first['options'][0]['model'] ?? '');
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
            ->assertSee('42 warehouse product(s)');
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

    public function test_preview_listing_shows_required_columns_and_variants_button(): void
    {
        $this->activeConnection();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'));

        $this->actingAs($user)
            ->get(route('product-map.index'))
            ->assertOk()
            ->assertSee('OC Product ID')
            ->assertSee('Product Image')
            ->assertSee('Variants / Action')
            ->assertSee('Variants (5)')
            ->assertDontSee('Advanced Diagnostics');
    }

    public function test_negative_stock_marks_health_review(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends \App\Services\OpenCart\ProductPreviewService
        {
            public function healthFor(array $product): array
            {
                return $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'))['health'];
            }
        };

        $health = $service->healthFor([
            'model' => 'NEG-001',
            'image_url' => 'https://example.com/p.jpg',
            'stock' => -3,
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
