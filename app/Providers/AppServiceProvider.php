<?php

namespace App\Providers;

use App\Models\Connection;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Policies\OrderPolicy;
use App\Policies\SupplierProductPolicy;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\OpenCartHttpClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpenCartHttpClient::class, function ($app) {
            return new OpenCartHttpClient(
                $app->make(ConnectionService::class)->getActive()
            );
        });
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(SupplierProduct::class, SupplierProductPolicy::class);
    }
}
