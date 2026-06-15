<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Services\OpenCart\OpenCartHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenCartHttpClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_use_api_token_query_parameter_not_bearer_header(): void
    {
        Http::fake([
            'https://store.example.com/*' => Http::response(['success' => true], 200),
        ]);

        $connection = Connection::getInstance();
        $connection->forceFill([
            'store_url' => 'https://store.example.com',
            'api_token' => 'secret-token',
            'product_api_endpoint' => 'index.php?route=api/ibs/products',
            'order_api_endpoint' => 'index.php?route=api/ibs/orders',
            'order_status_api_endpoint' => 'index.php?route=api/ibs/order_queue_statuses',
        ]);

        config(['dropflow.oc_mock' => false, 'dropflow.live_read_only' => false]);

        $client = new OpenCartHttpClient($connection);
        $client->readSample($connection->product_api_endpoint);

        Http::assertSent(function ($request) {
            $url = $request->url();
            $hasQueryToken = str_contains($url, 'api_token=secret-token');
            $hasBearer = $request->hasHeader('Authorization', 'Bearer secret-token');

            return $hasQueryToken && ! $hasBearer;
        });
    }
}
