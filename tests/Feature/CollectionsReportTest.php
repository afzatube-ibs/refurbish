<?php

namespace Tests\Feature;

use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CollectionsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        $this->supplier = Supplier::query()->firstOrFail();
        Connection::getInstance()->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'collections-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_collections_report_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.collections'))
            ->assertOk()
            ->assertSee('Collections Report', false)
            ->assertSee('Received by Supplier', false)
            ->assertSee('Payment to Dropshipper', false)
            ->assertSee('Adjustment', false)
            ->assertDontSee('Paid to store owner', false)
            ->assertDontSee('Received from supplier', false);
    }

    public function test_can_create_received_by_supplier_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.collections.store'), [
                'entry_type' => 'received_by_supplier',
                'collection_source' => 'cod',
                'entry_date' => '2026-06-15',
                'amount' => 500,
                'reference' => 'COD-001',
            ])
            ->assertRedirect(route('reports.collections'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settlement_entries', [
            'supplier_id' => $this->supplier->id,
            'entry_type' => SettlementEntryType::PaidToStoreOwner->value,
            'amount' => 500,
            'collection_source' => 'cod',
        ]);
    }

    public function test_can_create_payment_to_dropshipper_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.collections.store'), [
                'supplier_id' => $this->supplier->id,
                'entry_type' => 'payment_to_dropshipper',
                'collection_source' => 'bank',
                'entry_date' => '2026-06-15',
                'amount' => 300,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settlement_entries', [
            'entry_type' => SettlementEntryType::ReceivedFromSupplier->value,
            'amount' => 300,
        ]);
    }

    public function test_can_create_adjustment_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.collections.store'), [
                'supplier_id' => $this->supplier->id,
                'entry_type' => 'adjustment',
                'entry_date' => '2026-06-15',
                'amount' => -25,
                'notes' => 'Correction',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settlement_entries', [
            'entry_type' => SettlementEntryType::Adjustment->value,
            'amount' => -25,
        ]);
    }

    public function test_list_shows_operational_labels_not_legacy(): void
    {
        SettlementEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'connection_id' => Connection::getInstance()->id,
            'entry_type' => SettlementEntryType::PaidToStoreOwner,
            'amount' => 100,
            'entry_date' => '2026-06-10',
            'recorded_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.collections'))
            ->assertOk()
            ->assertSee('Received by Supplier', false)
            ->assertDontSee('Paid to store owner', false)
            ->assertDontSee('Received from supplier', false);
    }
}
