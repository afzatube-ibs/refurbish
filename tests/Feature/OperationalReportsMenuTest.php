<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalReportsMenuTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        Connection::getInstance()->update([
            'store_url' => 'https://www.staging.lokkisona.com',
            'is_active' => true,
        ]);

        config(['dropflow.modules.order_map' => true, 'dropflow.modules.product_map' => true]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'ops-menu-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_sidebar_shows_operational_reports(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dispatch Report', false)
            ->assertSee('Returns Report', false)
            ->assertSee('Collections Report', false)
            ->assertSee('Payables Report', false)
            ->assertSee('Order Queue', false)
            ->assertSee('Manual Order', false);
    }

    public function test_sidebar_hides_legacy_finance_from_main_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $html = $response->getContent();
        $reportsPos = strpos($html, 'Dispatch Report');
        $legacyPos = strpos($html, 'Legacy / Draft');

        $this->assertNotFalse($reportsPos);
        $this->assertNotFalse($legacyPos);
        $this->assertLessThan($legacyPos, $reportsPos);

        $mainReportsSection = substr($html, $reportsPos, $legacyPos - $reportsPos);
        $this->assertStringNotContainsString('Account Statement', $mainReportsSection);
        $this->assertStringNotContainsString('Settlement History', $mainReportsSection);
        $this->assertStringNotContainsString('Supplier Ledger', $mainReportsSection);
    }

    public function test_legacy_section_still_links_old_routes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Legacy / Draft', false)
            ->assertSee('Account Statement', false)
            ->assertSee('Settlement History', false);
    }
}
