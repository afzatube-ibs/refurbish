<?php

/**
 * One-off ops helper: sync OC statuses, map #25 as import_trigger, run connector audit.
 * Usage: php scripts/run-order-connector-audit.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\OrderStatusMapping;
use App\Models\User;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\OpenCartHttpClient;
use App\Services\OpenCart\OrderStatusService;
use App\Services\OrderMap\OrderConnectorAuditService;
use Illuminate\Support\Facades\Http;

$connectionService = app(ConnectionService::class);
$connectionService->assertSyncAllowed();

$connection = Connection::getInstance();
$client = new OpenCartHttpClient($connection);

echo "Store: {$connection->store_url}\n\n";

// Pre-deploy connector check (no secrets in output)
$versionUrl = rtrim($connection->store_url, '/').'/index.php?route=api/ibs/version&api_token='.urlencode($connection->api_token);
$version = Http::timeout(20)->get($versionUrl)->json();
echo "Connector version: ".($version['connector_version'] ?? 'unknown')."\n";
echo "Connector build: ".($version['connector_build'] ?? 'MISSING')."\n";
echo "Orders filter mode: ".($version['orders_filter_mode'] ?? 'MISSING')."\n\n";

$statusService = new OrderStatusService($client);
$fetch = $statusService->fetchFromOpenCart();
echo "Synced {$fetch['imported']} order statuses from OpenCart.\n";

$mapping = OrderStatusMapping::query()->firstOrNew(['source_status_id' => 25]);
if (! $mapping->exists) {
    $mapping->source_status_name = $mapping->source_status_name ?: 'Status #25';
}
$mapping->oc_selected = true;
$mapping->sfm_status = SfmOrderStatus::New;
$mapping->sync_role = OrderSyncRole::ImportTrigger;
$mapping->save();

echo "Mapped status #25 ({$mapping->source_status_name}) as import_trigger → New.\n\n";

$admin = User::query()->where('role', 'admin')->first();
$auditService = app(OrderConnectorAuditService::class);

try {
    $audit = $auditService->runImportTriggerAudit($admin);
} catch (Throwable $e) {
    echo "Audit failed: {$e->getMessage()}\n";
    exit(1);
}

$fields = [
    'connector_build',
    'orders_filter_mode',
    'audit_route',
    'total_raw_orders',
    'total_after_filter',
    'orders_api_total',
    'total_returned_this_page',
];
foreach ($fields as $field) {
    $value = $audit[$field] ?? null;
    if (is_array($value)) {
        $value = json_encode($value);
    }
    echo "{$field}: {$value}\n";
}

$bridge = $audit['would_exclude_if_warehouse_bridge'] ?? [];
if (is_array($bridge)) {
    echo 'would_exclude_if_warehouse_bridge.count: '.(int) ($bridge['count'] ?? count($bridge['order_ids'] ?? []))."\n";
}

echo "\nstatus_breakdown: ".json_encode($audit['status_breakdown'] ?? [])."\n";
echo 'returned_order_ids: '.json_encode($audit['returned_order_ids'] ?? [])."\n";
echo "\nCompare total_raw_orders above to OpenCart admin count for status #25.\n";
