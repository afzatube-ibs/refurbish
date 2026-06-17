<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\ProductMap\ProductRateHistory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductMap\ProductControlRateResolver;
use Carbon\Carbon;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductControlRateResolverTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
        Connection::getInstance()->update([
            'supplier_filter' => 'ex-a',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->where('code', 'EXA')->firstOrFail();
        $this->user = User::create([
            'name' => 'Resolver Admin',
            'email' => 'resolver-admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    public function test_rate_at_returns_latest_row_on_or_before_as_of(): void
    {
        $firstAt = Carbon::parse('2026-01-10 10:00:00');
        $secondAt = Carbon::parse('2026-01-20 10:00:00');

        ProductRateHistory::query()->create([
            'supplier_id' => $this->supplier->id,
            'product_id' => '9001',
            'variant_id' => null,
            'old_rate' => null,
            'new_rate' => 100,
            'difference' => 100,
            'effective_from' => $firstAt,
            'changed_by' => $this->user->id,
        ]);

        ProductRateHistory::query()->create([
            'supplier_id' => $this->supplier->id,
            'product_id' => '9001',
            'variant_id' => null,
            'old_rate' => 100,
            'new_rate' => 200,
            'difference' => 100,
            'effective_from' => $secondAt,
            'changed_by' => $this->user->id,
        ]);

        $resolver = app(ProductControlRateResolver::class);

        $this->assertSame(100.0, $resolver->rateAt('9001', null, $firstAt->copy()->addHours(6), $this->supplier));
        $this->assertSame(100.0, $resolver->rateAt('9001', null, Carbon::parse('2026-01-15 12:00:00'), $this->supplier));
        $this->assertSame(200.0, $resolver->rateAt('9001', null, $secondAt, $this->supplier));
        $this->assertSame(200.0, $resolver->rateAt('9001', null, Carbon::parse('2026-02-01 00:00:00'), $this->supplier));
    }

    public function test_rate_at_scopes_by_variant_id(): void
    {
        $effectiveAt = Carbon::parse('2026-03-01 09:00:00');

        ProductRateHistory::query()->create([
            'supplier_id' => $this->supplier->id,
            'product_id' => '9002',
            'variant_id' => null,
            'old_rate' => null,
            'new_rate' => 50,
            'difference' => 50,
            'effective_from' => $effectiveAt,
            'changed_by' => $this->user->id,
        ]);

        ProductRateHistory::query()->create([
            'supplier_id' => $this->supplier->id,
            'product_id' => '9002',
            'variant_id' => 'VAR-A',
            'old_rate' => null,
            'new_rate' => 75,
            'difference' => 75,
            'effective_from' => $effectiveAt,
            'changed_by' => $this->user->id,
        ]);

        $resolver = app(ProductControlRateResolver::class);
        $asOf = $effectiveAt->copy()->addHour();

        $this->assertSame(50.0, $resolver->rateAt('9002', null, $asOf, $this->supplier));
        $this->assertSame(75.0, $resolver->rateAt('9002', 'VAR-A', $asOf, $this->supplier));
        $this->assertSame(50.0, $resolver->rateAt('9002', 'VAR-B', $asOf, $this->supplier));
    }

    public function test_rate_at_falls_back_to_parent_when_variant_has_no_history(): void
    {
        $effectiveAt = Carbon::parse('2026-04-01 09:00:00');

        ProductRateHistory::query()->create([
            'supplier_id' => $this->supplier->id,
            'product_id' => '9003',
            'variant_id' => null,
            'old_rate' => null,
            'new_rate' => 50,
            'difference' => 50,
            'effective_from' => $effectiveAt,
            'changed_by' => $this->user->id,
        ]);

        $resolver = app(ProductControlRateResolver::class);
        $asOf = $effectiveAt->copy()->addHour();

        $this->assertSame(50.0, $resolver->rateAt('9003', 'VAR-X', $asOf, $this->supplier));
    }

    public function test_rate_at_returns_null_when_no_history(): void
    {
        $resolver = app(ProductControlRateResolver::class);

        $this->assertNull($resolver->rateAt('missing', null, Carbon::now(), $this->supplier));
    }
}
