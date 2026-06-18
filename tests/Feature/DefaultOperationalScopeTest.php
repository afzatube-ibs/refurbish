<?php

namespace Tests\Feature;

use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DefaultOperationalScopeTest extends TestCase
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
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'default-scope-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_payables_report_defaults_to_current_supplier_and_store(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.payables'))
            ->assertOk()
            ->assertSee($this->supplier->name, false)
            ->assertSee('staging.lokkisona.com', false)
            ->assertDontSee('All suppliers', false);
    }

    public function test_dispatch_report_defaults_to_current_supplier(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.dispatch'))
            ->assertOk()
            ->assertDontSee('All suppliers', false);
    }

    public function test_collections_entry_without_supplier_id_uses_default(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.collections.store'), [
                'entry_type' => 'adjustment',
                'entry_date' => '2026-06-15',
                'amount' => 10,
                'notes' => 'Default scope test',
            ])
            ->assertRedirect(route('reports.collections'));

        $this->assertDatabaseHas('settlement_entries', [
            'supplier_id' => $this->supplier->id,
            'entry_type' => SettlementEntryType::Adjustment->value,
            'amount' => 10,
        ]);
    }

    public function test_collections_form_hides_supplier_when_single_supplier(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.collections'))
            ->assertOk()
            ->assertSee('Supplier: Ex-A', false)
            ->assertDontSee('Select supplier', false);
    }
}
