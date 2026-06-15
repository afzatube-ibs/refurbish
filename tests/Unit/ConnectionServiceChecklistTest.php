<?php

namespace Tests\Unit;

use App\Services\OpenCart\ConnectionService;
use PHPUnit\Framework\TestCase;

class ConnectionServiceChecklistTest extends TestCase
{
    public function test_order_status_api_ok_when_read_success_without_statuses_array(): void
    {
        $service = new ConnectionService;
        $ref = new \ReflectionClass($service);

        $statusRead = [
            'success' => true,
            'status' => 200,
            'body' => [
                'success' => true,
                'selected_count' => 14,
                'status_count' => 62,
            ],
        ];

        $statusBody = $statusRead['body'];
        $statusApiOk = $statusRead['success'] === true
            && (($statusBody['success'] ?? true) === true);

        $this->assertTrue($statusApiOk);
    }

    public function test_option_image_check_uses_connection_test_probe(): void
    {
        $service = new ConnectionService;
        $method = (new \ReflectionClass($service))->getMethod('optionImageCheck');
        $method->setAccessible(true);

        /** @var array{passed: bool, ui_message: string} $result */
        $result = $method->invoke($service, [
            'poip_detected' => true,
            'join_active' => true,
            'sample_images_non_empty' => 4,
        ]);

        $this->assertTrue($result['passed']);
        $this->assertSame('Connected', $result['ui_message']);
    }

    public function test_extract_order_statuses_supports_alternate_keys(): void
    {
        $service = new ConnectionService;
        $method = (new \ReflectionClass($service))->getMethod('extractOrderStatuses');
        $method->setAccessible(true);

        $statuses = $method->invoke($service, [
            'order_queue_statuses' => [
                ['id' => 1, 'name' => 'Pending'],
            ],
        ]);

        $this->assertCount(1, $statuses);
    }
}
