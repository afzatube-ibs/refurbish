<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusMapping;
use App\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class OrderMapQueueTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update(['is_active' => true]);

        $supplier = Supplier::query()->where('is_active', true)->firstOrFail();

        $order = Order::query()->create([
            'supplier_id' => $supplier->id,
            'source_order_id' => '5001',
            'customer_name' => 'Queue Customer',
            'customer_phone' => '+8801700000001',
            'customer_address' => 'Hidden address line',
            'sale_amount' => 1500,
            'current_oc_status' => 'Processing',
            'sfm_status' => SfmOrderStatus::New,
            'courier_status' => 'In Transit',
            'consignment_id' => 'CNS-5001',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '201',
            'product_name' => 'Test Chair',
            'model' => 'CH-01',
            'option_name' => 'Color',
            'option_value' => 'Brown',
            'variant_label' => 'Color: Brown',
            'quantity' => 2,
            'sale_price' => 750,
            'supplier_product_cost_snapshot' => 120.00,
            'cost_snapshotted_at' => now(),
            'item_status' => \App\Enums\OrderItemStatus::Active,
            'is_unmatched' => false,
            'source_variant_key' => 'CH-01',
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 2,
            'source_status_name' => 'Processing',
            'sfm_status' => SfmOrderStatus::New,
        ]);
    }

    public function test_order_queue_renders_required_columns(): void
    {
        $response = $this->actingAs($this->adminUser('order-map'))
            ->get(route('order-map.index'));

        $response->assertOk()
            ->assertSee('Order No')
            ->assertSee('Customer')
            ->assertSee('Product Card')
            ->assertSee('Total Qty')
            ->assertSee('Total Cost')
            ->assertSee('IBS Status')
            ->assertSee('Consignment ID')
            ->assertSee('Actions')
            ->assertSee('#5001')
            ->assertSee('Queue Customer')
            ->assertSee('Brown')
            ->assertSee('CNS-5001')
            ->assertSee('Print Invoice');
    }

    public function test_order_queue_hides_oc_status_courier_and_address(): void
    {
        $response = $this->actingAs($this->adminUser('order-map'))
            ->get(route('order-map.index'));

        $response->assertOk()
            ->assertDontSee('OC Status')
            ->assertDontSee('Courier Status')
            ->assertDontSee('Shipping Address')
            ->assertDontSee('Hidden address line')
            ->assertDontSee('In Transit')
            ->assertDontSee('Processing');
    }
}
