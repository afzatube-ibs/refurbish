<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\Supplier;
use App\Services\OrderMap\ManualOrderService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\Concerns\SeedsProductMapCatalog;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;
    use SeedsProductMapCatalog;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true, 'dropflow.modules.product_map' => true]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update([
            'is_active' => true,
            'store_url' => 'https://www.lokkisona.example',
            'supplier_filter' => 'ex-a',
        ]);

        $this->supplier = Supplier::query()->where('is_active', true)->firstOrFail();
    }

    public function test_create_order_page_renders_professional_sections(): void
    {
        $this->actingAs($this->adminUser('manual-order-create'))
            ->get(route('order-map.create'))
            ->assertOk()
            ->assertSee('Create Order', false)
            ->assertSee('Create inbox, phone, or offline supplier order', false)
            ->assertSee('Order Source', false)
            ->assertSee('Customer Details', false)
            ->assertSee('Order Items', false)
            ->assertSee('Totals', false)
            ->assertSee('Product search', false)
            ->assertSee('Lokkisona', false)
            ->assertSee('v0.8.2', false);
    }

    public function test_manual_order_can_be_created_with_free_text_item(): void
    {
        $user = $this->adminUser('manual-order-free-text');

        $this->actingAs($user)
            ->post(route('order-map.store'), [
                'source_store' => 'lokkisona',
                'source_type' => 'phone',
                'reference_note' => 'Caller ref 88',
                'customer_name' => 'Phone Customer',
                'customer_phone' => '+8801700000999',
                'customer_address' => '44 Offline Road',
                'city_zone' => 'Dhaka',
                'delivery_note' => 'Call before delivery',
                'items' => [
                    [
                        'source_product_id' => '',
                        'product_name' => 'Custom Gift Set',
                        'model' => 'GIFT-01',
                        'option' => 'Wrap: Yes',
                        'quantity' => 1,
                        'sale_price' => 2200,
                    ],
                ],
            ])
            ->assertRedirect(route('order-map.index'))
            ->assertSessionHas('success');

        $order = Order::query()->where('customer_name', 'Phone Customer')->firstOrFail();
        $this->assertStringStartsWith('MAN-', $order->source_order_id);
        $this->assertSame(SfmOrderStatus::New, $order->sfm_status);
        $this->assertSame('Manual', $order->current_oc_status);
        $this->assertSame('lokkisona', $order->source_snapshot['source_store'] ?? null);
        $this->assertSame('phone', $order->source_snapshot['source_type'] ?? null);
        $this->assertSame('manual', $order->source_snapshot['source'] ?? null);

        $item = $order->items()->firstOrFail();
        $this->assertSame('MANUAL', $item->source_product_id);
        $this->assertTrue($item->is_unmatched);
        $this->assertSame('Custom Gift Set', $item->product_name);
    }

    public function test_manual_order_appears_in_order_queue(): void
    {
        $user = $this->adminUser('manual-order-queue');

        $this->actingAs($user)
            ->post(route('order-map.store'), $this->validPayload())
            ->assertRedirect(route('order-map.index'));

        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('order-map.index'))
            ->assertOk()
            ->assertSee($order->source_order_id, false)
            ->assertSee('Queue Customer', false);
    }

    public function test_manual_order_invoice_route_works(): void
    {
        $user = $this->adminUser('manual-order-invoice');

        $this->actingAs($user)
            ->post(route('order-map.store'), $this->validPayload());

        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('order-map.print-invoice', $order))
            ->assertOk()
            ->assertSee('Lokkisona Baby Store', false)
            ->assertSee($order->source_order_id, false)
            ->assertSee('Queue Product', false);
    }

    public function test_product_search_finds_local_product_by_ibs_model_and_name(): void
    {
        $this->seedProductMapCatalog();

        ProductControlState::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_product_id' => '9509',
            'ibs_model' => 'IBS-9509',
            'sm_model' => 'SM-9509',
            'rate' => 99.50,
        ]);

        $user = $this->adminUser('manual-order-search');

        $this->actingAs($user)
            ->getJson(route('order-map.create.products.search', ['q' => 'IBS-9509']))
            ->assertOk()
            ->assertJsonPath('results.0.source_product_id', '9509')
            ->assertJsonPath('results.0.ibs_model', 'IBS-9509');

        $this->actingAs($user)
            ->getJson(route('order-map.create.products.search', ['q' => 'PARENT-9509']))
            ->assertOk()
            ->assertJsonPath('results.0.model', 'PARENT-9509');
    }

    public function test_manual_item_does_not_require_product_map(): void
    {
        $user = $this->adminUser('manual-order-no-map');

        $this->actingAs($user)
            ->post(route('order-map.store'), [
                'source_store' => 'lokkisona',
                'source_type' => 'offline',
                'customer_name' => 'No Map Customer',
                'customer_phone' => '+8801700000888',
                'customer_address' => 'No map address',
                'items' => [
                    [
                        'product_name' => 'Unlisted Product',
                        'quantity' => 2,
                        'sale_price' => 500,
                    ],
                ],
            ])
            ->assertRedirect(route('order-map.index'));

        $this->assertDatabaseHas('orders', ['customer_name' => 'No Map Customer']);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Unlisted Product', 'is_unmatched' => true]);
    }

    public function test_required_validation_works(): void
    {
        $this->actingAs($this->adminUser('manual-order-validation'))
            ->from(route('order-map.create'))
            ->post(route('order-map.store'), [])
            ->assertRedirect(route('order-map.create'))
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'customer_address', 'items']);
    }

    public function test_matched_product_from_map_attaches_cost(): void
    {
        $this->seedProductMapCatalog();

        ProductControlState::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_product_id' => '9509',
            'ibs_model' => 'IBS-9509',
            'rate' => 125.00,
        ]);

        $user = $this->adminUser('manual-order-matched');

        $this->actingAs($user)
            ->post(route('order-map.store'), [
                'source_store' => 'lokkisona',
                'source_type' => 'inbox',
                'customer_name' => 'Matched Customer',
                'customer_phone' => '+8801700000777',
                'customer_address' => 'Matched address',
                'items' => [
                    [
                        'source_product_id' => '9509',
                        'product_name' => 'Catalog Product',
                        'model' => 'PARENT-9509',
                        'quantity' => 1,
                        'sale_price' => 1500,
                    ],
                ],
            ])
            ->assertRedirect(route('order-map.index'));

        $item = Order::query()->where('customer_name', 'Matched Customer')->firstOrFail()->items()->firstOrFail();
        $this->assertFalse($item->is_unmatched);
        $this->assertSame('125.00', (string) $item->supplier_product_cost_snapshot);
    }

    public function test_manual_order_service_sequence_increments(): void
    {
        $user = $this->adminUser('manual-order-seq');
        $service = app(ManualOrderService::class);

        $first = $service->create([
            'source_store' => 'lokkisona',
            'source_type' => 'other',
            'customer_name' => 'Seq One',
            'customer_phone' => '01',
            'customer_address' => 'A',
            'items' => [['product_name' => 'P1', 'quantity' => 1, 'sale_price' => 10]],
        ], $user);

        $second = $service->create([
            'source_store' => 'lokkisona',
            'source_type' => 'other',
            'customer_name' => 'Seq Two',
            'customer_phone' => '02',
            'customer_address' => 'B',
            'items' => [['product_name' => 'P2', 'quantity' => 1, 'sale_price' => 20]],
        ], $user);

        $this->assertSame('MAN-00001', $first->source_order_id);
        $this->assertSame('MAN-00002', $second->source_order_id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(): array
    {
        return [
            'source_store' => 'lokkisona',
            'source_type' => 'inbox',
            'customer_name' => 'Queue Customer',
            'customer_phone' => '+8801700000666',
            'customer_address' => 'Queue address line',
            'items' => [
                [
                    'product_name' => 'Queue Product',
                    'model' => 'QP-1',
                    'quantity' => 1,
                    'sale_price' => 999,
                ],
            ],
        ];
    }
}
