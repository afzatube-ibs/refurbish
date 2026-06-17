<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Connection;
use Illuminate\Support\Facades\Http;

$c = Connection::getInstance();
$base = rtrim($c->store_url, '/');
$token = $c->api_token;

foreach (['version', 'connection_test'] as $route) {
    $url = $base.'/index.php?route=api/ibs/'.$route.'&api_token='.urlencode($token);
    echo "=== {$route} ===\n";
    $r = Http::timeout(20)->get($url);
    echo 'HTTP '.$r->status()."\n";
    $json = $r->json();
    if (is_array($json)) {
        $keys = ['connector_version', 'connector_build', 'orders_filter_mode', 'routes'];
        foreach ($keys as $k) {
            if (array_key_exists($k, $json)) {
                echo $k.': '.json_encode($json[$k])."\n";
            }
        }
        if (! isset($json['connector_build'])) {
            echo "connector_build: MISSING\n";
        }
    } else {
        echo substr($r->body(), 0, 500)."\n";
    }
    echo "\n";
}
