<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductRateHistory;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductControlMergeService;
use App\Services\ProductMap\ProductControlRateResolver;
use App\Services\ProductMap\ProductMapLocalControlService;
use Carbon\Carbon;
use Database\Seeders\SupplierSeeder;
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
        $this->seed(SupplierSeeder::class);
        Connection::getInstance()->update([
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);
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

    public function test_rate_set_persists_state_and_history(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 125.50],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.rate', 125.5);

        $state = ProductControlState::query()->where('source_product_id', '9509')->first();
        $this->assertNotNull($state);
        $this->assertSame('125.50', (string) $state->rate);

        $history = ProductRateHistory::query()->where('product_id', '9509')->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->old_rate);
        $this->assertSame('125.50', (string) $history[0]->new_rate);
        $this->assertSame('125.50', (string) $history[0]->difference);
    }

    public function test_rate_increase_appends_history_without_overwriting(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 100],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'increase', 'amount' => 25, 'note' => 'Supplier update'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.rate', 125);

        $history = ProductRateHistory::query()->where('product_id', '9509')->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('100.00', (string) $history[0]->new_rate);
        $this->assertSame('125.00', (string) $history[1]->new_rate);
        $this->assertSame('25.00', (string) $history[1]->difference);
        $this->assertSame('Supplier update', $history[1]->note);
    }

    public function test_initial_stock_set_does_not_require_reason(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 50],
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 12],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.options.0.ibs_stock', 12);

        $stock = StockAdjustmentHistory::query()->where('product_id', '9509')->get();
        $this->assertCount(1, $stock);
        $this->assertSame('PARENT-9509-1', $stock[0]->variant_id);
        $this->assertNull($stock[0]->old_stock);
        $this->assertSame(12, $stock[0]->new_stock);
        $this->assertSame(12, $stock[0]->difference);
        $this->assertNull($stock[0]->reason);
    }

    public function test_subsequent_stock_change_requires_reason_and_logs_history(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 50],
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 3],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 8],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 8, 'reason' => 'Correction'],
                ],
            ])
            ->assertOk();

        $stock = StockAdjustmentHistory::query()->where('product_id', '9509')->orderBy('id')->get();
        $this->assertCount(2, $stock);
        $this->assertNull($stock[0]->reason);
        $this->assertSame('Correction', $stock[1]->reason);
        $this->assertSame(3, $stock[1]->old_stock);
        $this->assertSame(8, $stock[1]->new_stock);
        $this->assertSame(5, $stock[1]->difference);
    }

    public function test_stock_adjustment_mode_requires_reason(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 50],
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 10],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'increase', 'amount' => 2],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'variant', 'index' => 0, 'field' => 'ibs_stock', 'mode' => 'increase', 'amount' => 2, 'reason' => 'Sent to Wholesale'],
                ],
            ])
            ->assertOk();

        $stock = StockAdjustmentHistory::query()->where('product_id', '9509')->orderBy('id')->get();
        $this->assertCount(2, $stock);
        $this->assertNull($stock[0]->reason);
        $this->assertSame('Sent to Wholesale', $stock[1]->reason);
        $this->assertSame(12, $stock[1]->new_stock);
        $this->assertSame(2, $stock[1]->difference);
    }

    public function test_variable_product_rejects_parent_stock(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'ibs_stock', 'mode' => 'set', 'value' => 99, 'reason' => 'Correction'],
                ],
            ])
            ->assertStatus(422);

        $this->assertSame(0, StockAdjustmentHistory::query()->count());
    }

    public function test_rate_at_returns_correct_historical_rate(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();
        $supplier = Supplier::query()->where('code', 'EXA')->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 100],
                ],
            ]);

        $firstEffective = ProductRateHistory::query()->first()->effective_from;

        Carbon::setTestNow($firstEffective->copy()->addDay());

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 200],
                ],
            ]);

        $resolver = app(ProductControlRateResolver::class);
        $asOf = $firstEffective->copy()->addHours(12);

        $this->assertSame(100.0, $resolver->rateAt('9509', null, $asOf, $supplier));
        $this->assertSame(200.0, $resolver->rateAt('9509', null, Carbon::now(), $supplier));

        Carbon::setTestNow();
    }

    public function test_merge_on_refresh_preserves_db_rate(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 88.5],
                ],
            ]);

        $fresh = [
            'products' => [
                [
                    'product_id' => '9509',
                    'oc_product_id' => '9509',
                    'rate' => null,
                    'options' => [],
                ],
            ],
            'meta' => [],
            'summary' => [],
        ];

        $merged = app(ProductControlMergeService::class)->mergeIntoPreview($fresh);

        $this->assertSame(88.5, $merged['products'][0]['rate'] ?? null);
    }

    public function test_control_history_endpoint_returns_entries(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 50],
                ],
            ]);

        $this->actingAs($this->adminUser())
            ->getJson(route('product-map.control.history', ['product_id' => '9509']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'history.rate');
    }

    public function test_stock_reasons_match_allowed_list(): void
    {
        $this->assertSame(
            ['Sent to Wholesale', 'Correction'],
            ProductMapLocalControlService::STOCK_REASONS
        );
    }

    public function test_variant_rate_override_persists_and_history(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 100],
                    ['scope' => 'variant', 'index' => 0, 'field' => 'rate', 'mode' => 'set', 'value' => 125],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.options.0.rate', 125);

        $history = ProductRateHistory::query()->where('product_id', '9509')->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('PARENT-9509-1', $history[1]->variant_id);
        $this->assertSame('125.00', (string) $history[1]->new_rate);
    }

    public function test_product_category_persists_on_state(): void
    {
        $this->seedPreviewSession();

        $this->actingAs($this->adminUser())
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'product_category', 'mode' => 'set', 'value' => 'Chair'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('product.product_category', 'Chair');

        $state = ProductControlState::query()->where('source_product_id', '9509')->first();
        $this->assertSame('Chair', $state->product_category);
    }

    public function test_rate_history_rows_are_never_updated(): void
    {
        $this->seedPreviewSession();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 100],
                ],
            ])
            ->assertOk();

        $first = ProductRateHistory::query()->firstOrFail();
        $firstId = $first->id;

        $this->actingAs($user)
            ->postJson(route('product-map.control.save'), [
                'product_index' => 0,
                'changes' => [
                    ['scope' => 'parent', 'field' => 'rate', 'mode' => 'set', 'value' => 200],
                ],
            ])
            ->assertOk();

        $first->refresh();
        $this->assertSame('100.00', (string) $first->new_rate);
        $this->assertSame($firstId, $first->id);
        $this->assertSame(2, ProductRateHistory::query()->count());
    }

    public function test_low_qty_inherit_uses_parent_then_default(): void
    {
        $preview = $this->seedPreviewSession();
        $previewService = app(ProductPreviewService::class);

        $option = $preview['products'][0]['options'][0];
        $this->assertSame(5, $previewService->optionLowWarning($option, 5));

        $option['low_warning'] = 8;
        $this->assertSame(8, $previewService->optionLowWarning($option, 5));

        $option['low_warning'] = null;
        $this->assertSame(12, $previewService->optionLowWarning($option, 12));
    }
}
