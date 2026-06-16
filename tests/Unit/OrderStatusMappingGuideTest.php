<?php

namespace Tests\Unit;

use App\Enums\SfmOrderStatus;
use App\Models\OrderStatusMapping;
use App\Services\OrderMap\OrderStatusMappingGuide;
use Tests\TestCase;

class OrderStatusMappingGuideTest extends TestCase
{
    public function test_detects_dangerous_mapping_change(): void
    {
        $guide = new OrderStatusMappingGuide;

        $mapping = new OrderStatusMapping([
            'source_status_id' => 25,
            'source_status_name' => 'From Warehouse',
            'oc_selected' => true,
        ]);

        $this->assertTrue($guide->isDangerousSelection($mapping, 'rejected'));
        $this->assertFalse($guide->isDangerousSelection($mapping, 'new'));
    }

    public function test_recommended_rows_include_live_queue_pairings(): void
    {
        $guide = new OrderStatusMappingGuide;
        $rows = $guide->recommendedRows();

        $this->assertCount(5, $rows);
        $this->assertSame(25, $rows[0]['oc_id']);
        $this->assertSame(SfmOrderStatus::New, $rows[0]['ibs']);
        $this->assertSame(\App\Enums\OrderSyncRole::ImportTrigger, $rows[0]['sync_role']);
    }
}
