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
        $this->assertTrue($engine->canTransition(SfmOrderStatus::New, SfmOrderStatus::Rejected));
        $this->assertTrue($engine->canTransition(SfmOrderStatus::Dispatched, SfmOrderStatus::ReturnQueue));
        $this->assertFalse($engine->canTransition(SfmOrderStatus::New, SfmOrderStatus::Dispatched));
    }

    public function test_source_update_allowed_only_for_accepted_and_packed(): void
    {
        $engine = new OrderStatusEngine;

        $accepted = new \App\Models\Order(['sfm_status' => SfmOrderStatus::Accepted]);
        $new = new \App\Models\Order(['sfm_status' => SfmOrderStatus::New]);

        $this->assertTrue($engine->canUpdateFromSource($accepted));
        $this->assertFalse($engine->canUpdateFromSource($new));
    }
}
