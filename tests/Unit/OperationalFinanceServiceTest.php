<?php

namespace Tests\Unit;

use App\Services\OperationalFinanceService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationalFinanceServiceTest extends TestCase
{
    private OperationalFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OperationalFinanceService::class);
    }

    public function test_current_payable_formula(): void
    {
        $payable = $this->service->applyFormula(
            dispatchCost: 1000,
            returnCost: 100,
            receivedBySupplier: 200,
            paymentToDropshipper: 300,
            adjustment: 50,
        );

        $this->assertSame(450.0, $payable);
    }

    #[DataProvider('meaningProvider')]
    public function test_payable_meaning_labels(float $amount, string $expected): void
    {
        $this->assertSame($expected, $this->service->payableMeaning($amount));
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function meaningProvider(): array
    {
        return [
            'positive' => [100.0, 'Need to pay supplier'],
            'zero' => [0.0, 'Settled'],
            'negative' => [-50.0, 'Overpaid / review needed'],
        ];
    }
}
