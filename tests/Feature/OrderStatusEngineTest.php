<?php

namespace Tests\Feature;

use App\Enums\SfmOrderStatus;
use App\Services\OrderStatusEngine;
use Tests\TestCase;

class OrderStatusEngineTest extends TestCase
{
    public function test_supplier_workflow_transitions(): void
    {
        $engine = new OrderStatusEngine;

        $this->assertTrue($engine->canTransition(SfmOrderStatus::New, SfmOrderStatus::Accepted));
        $this->assertTrue($engine->canTransition(SfmOrderStatus::Accepted, SfmOrderStatus::Packed));
        $this->assertTrue($engine->canTransition(SfmOrderStatus::Packed, SfmOrderStatus::Dispatched));
        $this->assertFalse($engine->canTransition(SfmOrderStatus::New, SfmOrderStatus::Dispatched));
    }

    public function test_oc_status_never_moves_backward(): void
    {
        $engine = new OrderStatusEngine;
        $order = new \App\Models\Order(['sfm_status' => SfmOrderStatus::Dispatched]);

        $merged = $engine->mergeOcStatus($order, SfmOrderStatus::New);

        $this->assertSame(SfmOrderStatus::Dispatched, $merged);
    }
}
