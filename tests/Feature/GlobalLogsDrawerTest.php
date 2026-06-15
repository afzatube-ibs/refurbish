<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GlobalLogsDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-logs@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_logs_button_appears_on_dashboard(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="logs-drawer-open"', false)
            ->assertSee('Logs &amp; Diagnostics', false);
    }

    public function test_connection_page_has_no_advanced_diagnostics_panel(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('connection.edit'))
            ->assertOk()
            ->assertSee('Connection Status')
            ->assertDontSee('Advanced Diagnostics');
    }

    public function test_clear_connection_logs_opens_logs_drawer_on_connection_tab(): void
    {
        $admin = $this->adminUser();

        $payload = [
            'store_url' => 'https://store.example.com',
            'api_token' => 'test-token',
            'product_api_endpoint' => 'index.php?route=api/ibs/products',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => '1',
        ];

        $this->actingAs($admin)->post(route('connection.test'), $payload);

        $this->actingAs($admin)
            ->from(route('connection.edit'))
            ->post(route('connection.clear-logs'))
            ->assertRedirect(route('connection.edit'))
            ->assertSessionMissing('test_results')
            ->assertSessionHas('logs_tab', 'connection');
    }
}
