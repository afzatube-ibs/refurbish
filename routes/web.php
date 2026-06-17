<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductMapController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\ConnectionController;
use App\Http\Controllers\Settings\OrderStatusMappingController;
use App\Models\ReturnModel;
use Illuminate\Support\Facades\Route;

Route::bind('return', fn (string $value) => ReturnModel::findOrFail($value));

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['admin'])->group(function () {
        Route::get('/connection', [ConnectionController::class, 'edit'])->name('connection.edit');
        Route::post('/connection/save', [ConnectionController::class, 'update'])->name('connection.update');
        Route::post('/connection/test', [ConnectionController::class, 'test'])->name('connection.test');
        Route::post('/connection/clear-logs', [ConnectionController::class, 'clearLogs'])->name('connection.clear-logs');

        Route::redirect('/settings/connection', '/connection');

        Route::middleware(['module:order_map'])->prefix('settings/order-status-mapping')->name('settings.order-status-mapping.')->group(function () {
            Route::get('/', [OrderStatusMappingController::class, 'index'])->name('index');
            Route::post('/sync', [OrderStatusMappingController::class, 'syncFromOpenCart'])->name('sync');
            Route::put('/', [OrderStatusMappingController::class, 'update'])->name('update');
        });
    });

    Route::middleware(['admin', 'module:product_map'])->prefix('product-map')->name('product-map.')->group(function () {
        Route::get('/', [ProductMapController::class, 'index'])->name('index');
        Route::post('/load', [ProductMapController::class, 'load'])->name('load');
        Route::post('/load/confirm', [ProductMapController::class, 'confirmLoad'])->name('load.confirm');
        Route::post('/load/cancel', [ProductMapController::class, 'cancelLoad'])->name('load.cancel');
        Route::post('/refresh', [ProductMapController::class, 'refresh'])->name('refresh');
        Route::post('/control', [ProductMapController::class, 'saveControl'])->name('control.save');
        Route::get('/control/history', [ProductMapController::class, 'controlHistory'])->name('control.history');
        Route::post('/clear-logs', [LogsController::class, 'clearProductMapLogs'])->name('clear-logs');
        Route::post('/reset', [LogsController::class, 'resetProductMap'])->name('reset');
    });

    Route::middleware(['module:order_map'])->prefix('order-map')->name('order-map.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');

        Route::middleware(['admin'])->group(function () {
            Route::get('/create', [OrderController::class, 'create'])->name('create');
            Route::get('/create/products/search', [OrderController::class, 'searchManualProducts'])->name('create.products.search');
            Route::post('/create', [OrderController::class, 'store'])->name('store');
            Route::post('/load', [OrderController::class, 'load'])->name('load');
            Route::post('/sync-updates', [OrderController::class, 'syncStatusUpdates'])->name('sync-updates');
            Route::post('/audit-connector', [OrderController::class, 'auditConnector'])->name('audit-connector');
            Route::post('/sync', [OrderController::class, 'sync'])->name('sync');
        });

        Route::get('/{order}/print-invoice', [OrderController::class, 'printInvoice'])->name('print-invoice');
        Route::get('/{order}/panel', [OrderController::class, 'panel'])->name('panel');
        Route::put('/{order}', [OrderController::class, 'update'])->name('update');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');

        Route::middleware(['supplier'])->group(function () {
            Route::post('/{order}/accept', [OrderController::class, 'accept'])->name('accept');
            Route::post('/{order}/pack', [OrderController::class, 'pack'])->name('pack');
            Route::post('/{order}/dispatch', [OrderController::class, 'dispatch'])->name('dispatch');
            Route::post('/{order}/reject', [OrderController::class, 'reject'])->name('reject');
            Route::post('/{order}/return-queue', [OrderController::class, 'returnQueue'])->name('return-queue');
            Route::post('/{order}/return-received', [OrderController::class, 'returnReceived'])->name('return-received');
            Route::post('/{order}/complete', [OrderController::class, 'complete'])->name('complete');
            Route::post('/{order}/cancel', [OrderController::class, 'reject'])->name('cancel');
        });
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/dispatch', [ReportController::class, 'dispatch'])->name('dispatch');
        Route::get('/returns', [ReportController::class, 'returns'])->name('returns');

        Route::middleware(['admin'])->group(function () {
            Route::get('/ledger', [ReportController::class, 'ledger'])->name('ledger');
            Route::get('/payables', [ReportController::class, 'payables'])->name('payables');
            Route::get('/product-movement', [ReportController::class, 'productMovement'])->name('product-movement');
            Route::get('/profit-cost', [ReportController::class, 'profitCost'])->name('profit-cost');
            Route::get('/stock', [ReportController::class, 'stock'])->name('stock');
            Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
        });
    });
});

require __DIR__.'/auth.php';
