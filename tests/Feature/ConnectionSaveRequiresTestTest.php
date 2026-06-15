<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConnectionSaveRequiresTestTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_save_blocked_without_passing_connection_test(): void
    {
        $admin = $this->adminUser();

        $payload = [
            'store_url' => 'https://store.example.com',
            'api_token' => 'test-token',
            'product_api_endpoint' => '/products',
            'order_api_endpoint' => '/orders',
            'order_status_api_endpoint' => '/statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => '1',
        ];

        $this->actingAs($admin)
            ->post(route('connection.update'), $payload)
            ->assertRedirect(route('connection.edit', ['edit' => 1]))
            ->assertSessionHas('error');
    }

    public function test_save_allowed_after_all_checks_pass(): void
    {
        $admin = $this->adminUser();

        $payload = [
            'store_url' => 'https://store.example.com',
            'api_token' => 'test-token',
            'product_api_endpoint' => 'index.php?route=extension/dropflow/products',
            'order_api_endpoint' => 'index.php?route=extension/dropflow/orders',
            'order_status_api_endpoint' => 'index.php?route=extension/dropflow/order_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => '1',
        ];

        $this->actingAs($admin)
            ->post(route('connection.test'), $payload)
            ->assertRedirect(route('connection.edit', ['edit' => 1]))
            ->assertSessionHas('test_results')
            ->assertSessionHas('connection_verified_fingerprint')
            ->assertSessionHas('connection_pending_api_token', 'test-token');

        unset($payload['api_token']);

        $this->actingAs($admin)
            ->post(route('connection.update'), $payload)
            ->assertRedirect(route('connection.edit'))
            ->assertSessionHas('success');

        $connection = Connection::getInstance()->fresh();
        $this->assertSame('https://store.example.com', $connection->store_url);
        $this->assertSame('test-token', $connection->api_token);
    }

    public function test_first_save_with_blank_token_field_after_test(): void
    {
        $admin = $this->adminUser();

        $testPayload = [
            'store_url' => 'https://live-store.example.com',
            'api_token' => 'live-secret-token',
            'product_api_endpoint' => 'index.php?route=api/ibs/products',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
            'supplier_filter' => 'ex-a',
            'is_active' => '1',
        ];

        $this->actingAs($admin)->post(route('connection.test'), $testPayload);

        $savePayload = $testPayload;
        unset($savePayload['api_token']);

        $this->actingAs($admin)
            ->post(route('connection.update'), $savePayload)
            ->assertRedirect(route('connection.edit'))
            ->assertSessionHas('success');

        $this->assertSame('live-secret-token', Connection::getInstance()->fresh()->api_token);
    }

    public function test_save_page_shows_save_enabled_after_successful_test(): void
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
            ->get(route('connection.edit', ['edit' => 1]))
            ->assertOk()
            ->assertDontSee('id="save-connection-btn" disabled', false);
    }

    public function test_read_only_view_after_save_with_masked_token(): void
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
        $this->actingAs($admin)->post(route('connection.update'), $payload);

        $this->actingAs($admin)
            ->get(route('connection.edit'))
            ->assertOk()
            ->assertSee('••••••••••••')
            ->assertSee('Edit Connection')
            ->assertSee('Saved connection');
    }

    public function test_clear_logs_removes_test_results_only(): void
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
            ->post(route('connection.clear-logs'))
            ->assertRedirect(route('connection.edit'))
            ->assertSessionMissing('test_results')
            ->assertSessionHas('info');
    }
}
