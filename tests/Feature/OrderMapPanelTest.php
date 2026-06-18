<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class OrderMapPanelTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

    private Order $order;

    private User $supplierUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update(['is_active' => true]);

        $supplier = Supplier::query()->where('is_active', true)->firstOrFail();
        $this->supplierUser = User::query()->where('email', 'supplier@ex-a.com')->firstOrFail();

        $this->order = Order::query()->create([
            'supplier_id' => $supplier->id,
            'source_order_id' => '6001',
            'customer_name' => 'Panel Customer',
            'customer_phone' => '+8801700000099',
            'customer_address' => 'Panel address',
            'sale_amount' => 900,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => SfmOrderStatus::Accepted,
            'notes' => 'Handle with care',
        ]);

        OrderItem::query()->create([
            'order_id' => $this->order->id,
            'source_product_id' => '301',
            'product_name' => 'Panel Lamp',
            'model' => 'LP-01',
            'quantity' => 1,
            'sale_price' => 900,
            'item_status' => \App\Enums\OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);
    }

    public function test_panel_returns_detail_partial(): void
    {
        $response = $this->actingAs($this->adminUser('order-map-panel'))
            ->get(route('order-map.panel', $this->order));

        $response->assertOk()
            ->assertSee('order-map-detail-panel', false)
            ->assertSee('Panel Customer')
            ->assertSee('Panel Lamp')
            ->assertSee('Products', false)
            ->assertSee('Fulfillment', false)
            ->assertSee('Print Invoice', false);
    }

    public function test_index_view_button_opens_panel_modal_markup(): void
    {
        $response = $this->actingAs($this->adminUser('order-map-panel'))
            ->get(route('order-map.index'));

        $response->assertOk()
            ->assertSee('orderMapPanelModal', false)
            ->assertSee('data-order-panel-open', false)
            ->assertSee('Supplier order queue and fulfillment workflow')
            ->assertSee('v0.9.1', false)
            ->assertSee(route('order-map.panel', $this->order), false);
    }

    public function test_supplier_can_update_editable_order(): void
    {
        $response = $this->actingAs($this->supplierUser)
            ->put(route('order-map.update', $this->order), [
                'customer_name' => 'Updated Customer',
                'customer_phone' => '+8801700000100',
                'customer_address' => 'Updated address',
                'notes' => 'Updated notes',
            ]);

        $response->assertRedirect();

        $this->order->refresh();

        $this->assertSame('Updated Customer', $this->order->customer_name);
        $this->assertSame('Updated notes', $this->order->notes);
    }

    public function test_supplier_cannot_update_new_order(): void
    {
        $this->order->update(['sfm_status' => SfmOrderStatus::New]);

        $response = $this->actingAs($this->supplierUser)
            ->put(route('order-map.update', $this->order), [
                'customer_name' => 'Blocked Customer',
                'customer_phone' => '+8801700000100',
                'customer_address' => 'Blocked address',
                'notes' => null,
            ]);

        $response->assertForbidden();
    }

    public function test_supplier_panel_includes_workflow_actions(): void
    {
        $this->order->update(['sfm_status' => SfmOrderStatus::New]);

        $response = $this->actingAs($this->supplierUser)
            ->get(route('order-map.panel', $this->order));

        $response->assertOk()
            ->assertSee('Accept', false)
            ->assertSee(route('order-map.accept', $this->order), false);
    }
}
