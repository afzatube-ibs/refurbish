<?php

namespace Tests\Feature;

use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SettlementService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayablesOperationalReportSeparationTest extends TestCase
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
            'email' => 'payables-sep-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_operational_payables_shows_settlement_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payables.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Record supplier payments and see current payable balance.', false)
            ->assertSee('Record Settlement', false)
            ->assertSee('Total Dispatched Cost', false)
            ->assertSee('Settlement History', false)
            ->assertDontSee('Payables Summary Report', false);
    }

    public function test_payables_report_does_not_show_settlement_form(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            25,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
        );

        $this->actingAs($this->admin)
            ->get(route('reports.payables'))
            ->assertOk()
            ->assertSee('Payables Summary Report', false)
            ->assertSee('Supplier Payable Summary', false)
            ->assertSee('Dispatch Cost', false)
            ->assertSee('Return Cost', false)
            ->assertSee('Current Balance', false)
            ->assertSee('Store', false)
            ->assertDontSee('Record Settlement', false)
            ->assertDontSee('Record settlement', false);
    }

    public function test_payables_report_shows_supplier_summary_columns(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.payables'))
            ->assertOk()
            ->assertSee('Supplier', false)
            ->assertSee($this->supplier->name, false)
            ->assertSee('staging.lokkisona.com', false);
    }

    public function test_payables_report_empty_state_message(): void
    {
        Supplier::query()->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('reports.payables'))
            ->assertOk()
            ->assertSee('No payable summary found for selected filters.', false);
    }

    public function test_account_statement_still_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.ledger', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Account Statement', false)
            ->assertSee('Current Balance', false);
    }
}
