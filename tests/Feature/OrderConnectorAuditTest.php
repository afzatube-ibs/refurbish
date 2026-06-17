<?php

namespace Tests\Feature;

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\OrderStatusMapping;
use App\Models\User;
use App\Services\OrderMap\OrderConnectorAuditService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderConnectorAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dropflow.modules.order_map' => true,
            'dropflow.oc_mock' => true,
            'dropflow.live_read_only' => false,
            'dropflow.allow_opencart_sync' => true,
        ]);

        $this->seed(SupplierSeeder::class);

        Connection::getInstance()->update([
            'store_url' => 'https://example.com',
            'api_token' => 'test-token',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);
    }

    protected function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'audit-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_audit_connector_stores_truth_panel_fields(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'sfm_status' => SfmOrderStatus::New,
            'sync_role' => OrderSyncRole::ImportTrigger,
            'oc_selected' => true,
        ]);

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('order-map.audit-connector'))
            ->assertRedirect(route('order-map.index'))
            ->assertSessionHas('success');

        $audit = session(OrderConnectorAuditService::SESSION_KEY);

        $this->assertIsArray($audit);
        $this->assertSame('v2-queue-status-only-audit2', $audit['connector_build']);
        $this->assertSame([25], $audit['requested_status_ids']);
        $this->assertSame(2, $audit['total_raw_orders']);
        $this->assertSame(2, $audit['total_after_filter']);
        $this->assertArrayHasKey('25', $audit['status_breakdown']);
        $this->assertNotEmpty($audit['returned_order_ids']);
        $this->assertArrayHasKey('would_exclude_if_warehouse_bridge', $audit);
    }

    public function test_audit_panel_renders_on_order_queue(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'sfm_status' => SfmOrderStatus::New,
            'sync_role' => OrderSyncRole::ImportTrigger,
            'oc_selected' => true,
        ]);

        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('order-map.audit-connector'));

        $this->actingAs($admin)
            ->get(route('order-map.index'))
            ->assertOk()
            ->assertSee('Connector Orders Audit', false)
            ->assertSee('total_raw_orders', false)
            ->assertSee('would_exclude_if_warehouse_bridge', false)
            ->assertSee('v2-queue-status-only-audit2', false);
    }
}
