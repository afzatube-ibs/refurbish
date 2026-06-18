<?php

namespace Tests\Unit;

use App\Services\PayableService;
use Tests\TestCase;

class PayableServiceBalanceMeaningTest extends TestCase
{
    private PayableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayableService::class);
    }

    public function test_positive_balance_meaning_and_tone(): void
    {
        $this->assertSame('Supplier needs to pay Lokkisona', $this->service->balanceMeaning(150.5));
        $this->assertSame('positive', $this->service->balanceTone(150.5));
        $this->assertSame('text-emerald-700', $this->service->balanceToneClass(150.5));
    }

    public function test_negative_balance_meaning_and_tone(): void
    {
        $this->assertSame('Lokkisona needs to pay supplier', $this->service->balanceMeaning(-25));
        $this->assertSame('negative', $this->service->balanceTone(-25));
        $this->assertSame('text-orange-600', $this->service->balanceToneClass(-25));
    }

    public function test_zero_balance_meaning_and_tone(): void
    {
        $this->assertSame('Settled', $this->service->balanceMeaning(0));
        $this->assertSame('zero', $this->service->balanceTone(0));
        $this->assertSame('text-emerald-700', $this->service->balanceToneClass(0));
    }

    public function test_balance_presentation_includes_all_fields(): void
    {
        $presentation = $this->service->balancePresentation(42.12);

        $this->assertSame(42.12, $presentation['amount']);
        $this->assertSame('Supplier needs to pay Lokkisona', $presentation['meaning']);
        $this->assertSame('positive', $presentation['tone']);
        $this->assertSame('text-emerald-700', $presentation['tone_class']);
    }

    public function test_closing_balance_uses_final_formula(): void
    {
        $balance = $this->service->closingBalance([
            'received_from_supplier' => 100,
            'paid_to_store_owner' => 40,
            'delivered_cost' => 200,
            'returned_cost' => 60,
            'adjustment_total' => 5,
        ]);

        $this->assertSame(-75.0, $balance);
    }

    public function test_build_report_row_includes_balance_meaning(): void
    {
        $row = $this->service->buildReportRow('Supplier A', 'Store B', [
            'delivered_cost' => 200,
            'returned_cost' => 50,
            'total_paid' => 25,
            'paid_to_store_owner' => 25,
            'received_from_supplier' => 0,
            'adjustment_total' => 0,
        ]);

        $this->assertSame(-175.0, $row['net_payable']);
        $this->assertSame('Lokkisona needs to pay supplier', $row['balance_meaning']);
        $this->assertSame('negative', $row['balance_tone']);
        $this->assertSame('text-orange-600', $row['balance_tone_class']);
    }
}
