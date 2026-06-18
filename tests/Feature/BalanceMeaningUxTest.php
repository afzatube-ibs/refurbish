<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\SettlementEntryType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\SettlementService;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BalanceMeaningUxTest extends TestCase
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
            'email' => 'balance-meaning-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_positive_balance_shows_payable_meaning_on_all_pages(): void
    {
        $this->postDispatchLedger(200);

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Payable to supplier', false)
            ->assertSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Payable to supplier', false)
            ->assertDontSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', $query))
            ->assertOk()
            ->assertSee('Payable to supplier', false)
            ->assertSee('Account Statement', false);
    }

    public function test_negative_balance_shows_receivable_meaning_on_all_pages(): void
    {
        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::ReceivedFromSupplier,
            75,
            new \DateTimeImmutable('2026-06-01'),
            $this->admin,
        );

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Receivable from supplier / advance paid', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Receivable from supplier / advance paid', false);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', $query))
            ->assertOk()
            ->assertSee('Receivable from supplier / advance paid', false);
    }

    public function test_zero_balance_shows_settled_meaning_on_all_pages(): void
    {
        $this->postDispatchLedger(100);

        app(SettlementService::class)->record(
            $this->supplier->id,
            SettlementEntryType::PaidToStoreOwner,
            100,
            new \DateTimeImmutable('2026-06-02'),
            $this->admin,
        );

        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Settled', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertSee('Settled', false);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', $query))
            ->assertOk()
            ->assertSee('Settled', false);
    }

    public function test_payables_settlement_form_shows_type_helper_text(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payables.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertSee('Money received by store owner from supplier or COD collection.', false)
            ->assertSee('Supplier paid or returned money to store owner.', false)
            ->assertSee('Manual correction — amount may be positive or negative.', false);
    }

    public function test_settlement_form_only_on_operational_payables_page(): void
    {
        $query = ['supplier_id' => $this->supplier->id];

        $this->actingAs($this->admin)
            ->get(route('payables.index', $query))
            ->assertOk()
            ->assertSee('Record Settlement', false);

        $this->actingAs($this->admin)
            ->get(route('reports.payables', $query))
            ->assertOk()
            ->assertDontSee('Record Settlement', false)
            ->assertSee('payables-balance-col', false);

        $this->actingAs($this->admin)
            ->get(route('reports.ledger', $query))
            ->assertOk()
            ->assertDontSee('Record Settlement', false);
    }

    private function postDispatchLedger(float $amount): void
    {
        $connection = Connection::getInstance();

        SupplierLedgerEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'connection_id' => $connection->id,
            'type' => LedgerEntryType::DispatchCost,
            'amount' => $amount,
            'entry_date' => now(),
            'reference' => 'TEST-DISPATCH',
            'notes' => 'Balance meaning test dispatch',
        ]);
    }
}
