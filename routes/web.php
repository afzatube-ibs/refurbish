<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductMapController;
use App\Http\Controllers\Settings\ConnectionController;
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
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');

        Route::middleware(['admin'])->group(function () {
            Route::post('/sync', [OrderController::class, 'sync'])->name('sync');
        });

        Route::middleware(['supplier'])->group(function () {
            Route::post('/{order}/accept', [OrderController::class, 'accept'])->name('accept');
            Route::post('/{order}/pack', [OrderController::class, 'pack'])->name('pack');
            Route::post('/{order}/dispatch', [OrderController::class, 'dispatch'])->name('dispatch');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
        });
    });
});

require __DIR__.'/auth.php';
