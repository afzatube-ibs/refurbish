<?php

/**
 * Probe staging connector + orders API for status #25 (no audit route required).
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Connection;
use Illuminate\Support\Facades\Http;

$c = Connection::getInstance();
$base = rtrim($c->store_url, '/');
$token = urlencode($c->api_token);

$version = Http::timeout(20)->get($base.'/index.php?route=api/ibs/version&api_token='.$token)->json();
echo "version.connector_build: ".($version['connector_build'] ?? 'MISSING')."\n";
echo "version.orders_filter_mode: ".($version['orders_filter_mode'] ?? 'MISSING')."\n\n";

$orders = Http::timeout(20)->get($base.'/index.php?route=api/ibs/orders&api_token='.$token.'&status_ids[]=25&page=1&limit=20');
echo "orders HTTP: ".$orders->status()."\n";
$body = $orders->json();
if (is_array($body)) {
    echo "orders.total: ".($body['total'] ?? 'n/a')."\n";
    echo "orders.filter_applied: ".($body['filter_applied'] ?? 'n/a')."\n";
    echo "orders.matched_order_count: ".($body['matched_order_count'] ?? 'n/a')."\n";
    echo "orders.returned: ".(is_array($body['orders'] ?? null) ? count($body['orders']) : 0)."\n";
} else {
    echo substr($orders->body(), 0, 300)."\n";
}
