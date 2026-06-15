<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductMapLocalControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductMapControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.product_map' => true]);
    }

    protected function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-control@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedPreviewSession(): array
    {
        $service = app(ProductPreviewService::class);
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
                    'options' => [
                        [
                            'model' => 'PARENT-9509-1',
                            'quantity' => 3,
                            'image' => 'catalog/opt.jpg',
                        ],
                    ],
                ], OpenCartImageContext::fromStoreUrl('https://example.com'));

                $products = $this->applyHealthRules([$product]);

                return [
                    'products' => $products,
                    'activity' => [],
                    'meta' => ['has_local_edits' => false],
                    'summary' => $this->buildSummary([[]], $products),
                    'diagnostics' => ['raw_product_count' => 1],
                ];
            }
        };

        $preview = $anonymous->buildSample();
        session(['product_preview' => $preview]);

        return $preview;
    }

    public function test_control_save_updates_local_fields_in_session(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'parent' => [
                    'ibs_model' => 'IBS-UPDATED',
                    'sm_model' => 'SM-001',
                    'low_warning' => 8,
                    'rate' => ['mode' => 'set', 'amount' => 125.50, 'note' => 'Manual set'],
                    'ibs_stock' => ['mode' => 'set', 'amount' => 20, 'reason' => 'Correction'],
                ],
                'variants' => [
                    [
                        'index' => 0,
                        'ibs_model' => 'IBS-VAR-1',
                        'sm_model' => 'SM-VAR-1',
                        'low_warning' => ['inherit' => true],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product.ibs_model', 'IBS-UPDATED')
            ->assertJsonPath('product.sm_model', 'SM-001')
            ->assertJsonPath('product.rate', 125.5)
            ->assertJsonPath('product.ibs_stock', 20)
            ->assertJsonPath('product.low_warning', 8);

        $preview = session('product_preview');
        $this->assertTrue($preview['meta']['has_local_edits'] ?? false);
        $this->assertGreaterThanOrEqual(5, count($preview['activity'] ?? []));
        $this->assertSame('IBS-UPDATED', $preview['products'][0]['ibs_model']);
        $this->assertNull($preview['products'][0]['options'][0]['low_warning']);
    }

    public function test_ibs_stock_change_requires_reason(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'parent' => [
                    'ibs_model' => 'IBS-9509',
                    'sm_model' => '',
                    'low_warning' => 5,
                    'ibs_stock' => ['mode' => 'increase', 'amount' => 2],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rate_increase_and_decrease_record_activity(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'parent' => [
                    'ibs_model' => 'IBS-9509',
                    'sm_model' => '',
                    'low_warning' => 5,
                    'rate' => ['mode' => 'set', 'amount' => 100],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'parent' => [
                    'ibs_model' => 'IBS-9509',
                    'sm_model' => '',
                    'low_warning' => 5,
                    'rate' => ['mode' => 'increase', 'amount' => 25, 'note' => 'Supplier update'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.rate', 125);

        $activity = session('product_preview.activity');
        $rateEntries = array_values(array_filter($activity, fn ($entry) => ($entry['field'] ?? '') === 'rate'));
        $this->assertGreaterThanOrEqual(2, count($rateEntries));
        $this->assertSame('increase', $rateEntries[1]['change_type'] ?? null);
    }

    public function test_health_recalculates_after_local_stock_change(): void
    {
        $this->seedPreviewSession();

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'parent' => [
                    'ibs_model' => 'IBS-9509',
                    'sm_model' => '',
                    'low_warning' => 10,
                ],
                'variants' => [
                    [
                        'index' => 0,
                        'ibs_model' => 'IBS-VAR-1',
                        'sm_model' => '',
                        'low_warning' => ['value' => 10],
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertSame('low', $response->json('product.health.status'));
        $this->assertSame('low', $response->json('product.options.0.health.status'));
    }

    public function test_stock_reasons_match_allowed_list(): void
    {
        $this->assertSame(
            ['Sent to Wholesale', 'Correction'],
            ProductMapLocalControlService::STOCK_REASONS
        );
    }
}
