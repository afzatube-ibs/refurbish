<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\OrderMap\ManualOrderService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class OrderMapPackingInvoiceTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update([
            'is_active' => true,
            'store_url' => 'https://www.lokkisona.example',
            'supplier_filter' => 'ex-a',
        ]);

        $this->supplier = Supplier::query()->where('is_active', true)->firstOrFail();
    }

    public function test_packing_invoice_renders_lokkisona_layout_customer_and_products(): void
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => '7001',
            'customer_name' => 'Invoice Customer',
            'customer_phone' => '+8801700000111',
            'customer_address' => "12 Packing Lane\nDhaka",
            'sale_amount' => 2850,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => SfmOrderStatus::Dispatched,
            'consignment_id' => 'CNS-7001',
            'courier_name' => 'Pathao',
            'oc_created_at' => now()->subDay(),
            'source_snapshot' => [
                'cod_amount' => 2850.00,
                'order_total' => 2850.00,
                'payment_method' => 'Cash On Delivery',
            ],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '401',
            'product_name' => 'Warehouse Chair',
            'model' => 'CH-01',
            'option_name' => 'Color',
            'option_value' => 'Brown',
            'variant_label' => 'Color: Brown',
            'quantity' => 2,
            'sale_price' => 750,
            'item_status' => \App\Enums\OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);

        $this->actingAs($this->adminUser('packing-invoice-lk'))
            ->get(route('order-map.print-invoice', $order))
            ->assertOk()
            ->assertSee('Lokkisona Baby Store', false)
            ->assertSee('ORDER #7001', false)
            ->assertSee('Customer Details', false)
            ->assertSee('Delivery Address', false)
            ->assertSee('Invoice Customer', false)
            ->assertSee('+8801700000111', false)
            ->assertSee('12 Packing Lane', false)
            ->assertSee('Warehouse Chair', false)
            ->assertSee('CH-01', false)
            ->assertSee('Color: Brown', false)
            ->assertSee('Consignment ID', false)
            ->assertSee('CNS-7001', false)
            ->assertSee('DUE', false)
            ->assertSee('2,850.00', false)
            ->assertSee('+8801932263545', false)
            ->assertSee('24 hours', false)
            ->assertSee('Back to Order Queue', false)
            ->assertSee('print-actions', false)
            ->assertSee('data-qr="https://steadfast.com.bd/t/CNS-7001"', false)
            ->assertDontSee('IBS', false)
            ->assertDontSee('Supplier fulfillment', false)
            ->assertDontSee('Supplier Ledger', false)
            ->assertDontSee('Profit', false)
            ->assertDontSee('settlement', false)
            ->assertDontSee('supplier_product_cost', false)
            ->assertDontSee('Store Name:', false)
            ->assertDontSee('unmatched', false);
    }

    public function test_packing_invoice_shows_paid_seal_for_bkash_payment(): void
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => '7003',
            'customer_name' => 'Bkash Customer',
            'customer_phone' => '+8801700000444',
            'customer_address' => 'Bkash address',
            'sale_amount' => 1500,
            'current_oc_status' => 'Processing',
            'current_oc_status_id' => 2,
            'sfm_status' => SfmOrderStatus::Dispatched,
            'oc_created_at' => now(),
            'source_snapshot' => [
                'payment_method' => 'bKash Payment',
                'order_total' => 1500.00,
            ],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'source_product_id' => '402',
            'product_name' => 'Paid Product',
            'model' => 'PD-01',
            'quantity' => 1,
            'sale_price' => 1500,
            'item_status' => \App\Enums\OrderItemStatus::Active,
            'is_unmatched' => false,
        ]);

        $this->actingAs($this->adminUser('packing-invoice-bkash'))
            ->get(route('order-map.print-invoice', $order))
            ->assertOk()
            ->assertSee('PAID', false)
            ->assertSee('seal-paid', false);
    }

    public function test_packing_invoice_uses_snapshot_tracking_url_for_qr_payload(): void
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => '7004',
            'customer_name' => 'Tracking Customer',
            'customer_phone' => '+8801700000555',
            'customer_address' => 'Tracking address',
            'sale_amount' => 900,
            'current_oc_status' => 'Shipped',
            'current_oc_status_id' => 3,
            'sfm_status' => SfmOrderStatus::Dispatched,
            'consignment_id' => 'CNS-TRACK',
            'oc_created_at' => now(),
            'source_snapshot' => [
                'tracking_url' => 'https://steadfast.com.bd/t/CUSTOM-TRACK',
                'order_total' => 900.00,
            ],
        ]);

        $this->actingAs($this->adminUser('packing-invoice-tracking'))
            ->get(route('order-map.print-invoice', $order))
            ->assertOk()
            ->assertSee('data-qr="https://steadfast.com.bd/t/CUSTOM-TRACK"', false);
    }

    public function test_packing_invoice_renders_manual_order(): void
    {
        $user = $this->adminUser('packing-invoice-manual');

        $order = app(ManualOrderService::class)->create([
            'customer_name' => 'Manual Invoice Customer',
            'customer_phone' => '+8801700000222',
            'customer_address' => 'Manual packing address',
            'items' => [
                [
                    'source_product_id' => '501',
                    'product_name' => 'Manual Lamp',
                    'model' => 'LP-MAN',
                    'quantity' => 1,
                    'sale_price' => 1200,
                ],
            ],
        ], $user);

        $this->actingAs($user)
            ->get(route('order-map.print-invoice', $order))
            ->assertOk()
            ->assertSee('Lokkisona Baby Store', false)
            ->assertSee('MAN-', false)
            ->assertSee('Manual Invoice Customer', false)
            ->assertSee('Manual Lamp', false)
            ->assertSee('LP-MAN', false)
            ->assertSee('1,200.00', false)
            ->assertSee('24 hours', false);
    }

    public function test_dispatched_order_modal_shows_print_invoice_action(): void
    {
        $order = Order::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_order_id' => '7002',
            'customer_name' => 'Dispatched Customer',
            'customer_phone' => '+8801700000333',
            'customer_address' => 'Dispatch address',
            'sale_amount' => 500,
            'current_oc_status' => 'Shipped',
            'current_oc_status_id' => 3,
            'sfm_status' => SfmOrderStatus::Dispatched,
            'consignment_id' => 'CNS-7002',
            'oc_created_at' => now(),
        ]);

        $supplierUser = \App\Models\User::query()->where('email', 'supplier@ex-a.com')->firstOrFail();

        $this->actingAs($supplierUser)
            ->get(route('order-map.panel', $order))
            ->assertOk()
            ->assertSee('Print Invoice', false)
            ->assertSee(route('order-map.print-invoice', $order), false);
    }
}
