<?php

namespace Tests\Feature;

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use App\Models\OrderStatusMapping;
use App\Services\OpenCart\OrderStatusService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUniqueAdminUser;
use Tests\TestCase;

class OrderStatusMappingPageTest extends TestCase
{
    use CreatesUniqueAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dropflow.modules.order_map' => true]);
        $this->seed(SupplierSeeder::class);
    }

    public function test_active_queue_statuses_shown_in_active_section(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'oc_selected' => true,
            'sfm_status' => SfmOrderStatus::New,
            'sync_role' => OrderSyncRole::ImportTrigger,
        ]);

        OrderStatusMapping::query()->create([
            'source_status_id' => 2,
            'source_status_name' => 'Processing',
            'oc_selected' => false,
            'sfm_status' => SfmOrderStatus::Ignore,
            'sync_role' => OrderSyncRole::Ignore,
        ]);

        $response = $this->actingAs($this->adminUser('order-status'))
            ->get(route('settings.order-status-mapping.index'));

        $response->assertOk()
            ->assertSee('Active Queue Statuses')
            ->assertSee('From Warehouse')
            ->assertSee('Import Trigger')
            ->assertSee('Create IBS order once + deduct stock')
            ->assertSee('How sync roles work')
            ->assertSee('Other OpenCart Statuses')
            ->assertSee('Recommended current mapping')
            ->assertSee('25 From Warehouse')
            ->assertSee('Save Mapping');
    }

    public function test_reference_statuses_are_collapsed_and_muted(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 2,
            'source_status_name' => 'Processing',
            'oc_selected' => false,
            'sfm_status' => SfmOrderStatus::Ignore,
            'sync_role' => OrderSyncRole::Ignore,
        ]);

        $response = $this->actingAs($this->adminUser('order-status'))
            ->get(route('settings.order-status-mapping.index'));

        $response->assertOk()
            ->assertSee('osm-reference-details', false)
            ->assertSee('not selected — reference only')
            ->assertSee('Processing');
    }

    public function test_save_forces_ignore_on_not_selected_statuses(): void
    {
        $selected = OrderStatusMapping::query()->create([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'oc_selected' => true,
            'sfm_status' => SfmOrderStatus::New,
            'sync_role' => OrderSyncRole::ImportTrigger,
        ]);

        $reference = OrderStatusMapping::query()->create([
            'source_status_id' => 5,
            'source_status_name' => 'Complete',
            'oc_selected' => false,
            'sfm_status' => SfmOrderStatus::Completed,
            'sync_role' => OrderSyncRole::UpdateExisting,
        ]);

        $this->actingAs($this->adminUser('order-status'))
            ->put(route('settings.order-status-mapping.update'), [
                'mappings' => [
                    ['id' => $selected->id, 'sfm_status' => 'new', 'sync_role' => 'import_trigger'],
                    ['id' => $reference->id, 'sfm_status' => 'completed', 'sync_role' => 'update_existing'],
                ],
            ])
            ->assertRedirect(route('settings.order-status-mapping.index'))
            ->assertSessionHas('success', 'Order status mappings saved.');

        $this->assertSame(SfmOrderStatus::Ignore, $reference->fresh()->sfm_status);
        $this->assertSame(OrderSyncRole::Ignore, $reference->fresh()->sync_role);
        $this->assertFalse($reference->fresh()->isSyncActive());
    }

    public function test_not_selected_statuses_do_not_affect_sync(): void
    {
        OrderStatusMapping::query()->create([
            'source_status_id' => 5,
            'source_status_name' => 'Complete',
            'oc_selected' => false,
            'sfm_status' => SfmOrderStatus::Completed,
            'sync_role' => OrderSyncRole::UpdateExisting,
        ]);

        $service = app(OrderStatusService::class);

        $this->assertSame(SfmOrderStatus::Ignore, $service->applyMapping(5));
        $this->assertSame(0, OrderStatusMapping::query()->syncActive()->count());
    }

    public function test_save_persists_selected_mapping_and_sync_role(): void
    {
        $mapping = OrderStatusMapping::query()->create([
            'source_status_id' => 7,
            'source_status_name' => 'Canceled',
            'oc_selected' => true,
            'sfm_status' => SfmOrderStatus::Ignore,
            'sync_role' => OrderSyncRole::Ignore,
        ]);

        $this->actingAs($this->adminUser('order-status'))
            ->put(route('settings.order-status-mapping.update'), [
                'mappings' => [
                    ['id' => $mapping->id, 'sfm_status' => 'rejected', 'sync_role' => 'update_existing'],
                ],
            ])
            ->assertSessionHas('success', 'Order status mappings saved.');

        $mapping->refresh();
        $this->assertSame(SfmOrderStatus::Rejected, $mapping->sfm_status);
        $this->assertSame(OrderSyncRole::UpdateExisting, $mapping->sync_role);
    }
}
