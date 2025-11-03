<?php

use App\Http\Controllers\Api\OrderController as ApiOrderController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return $request->user();
    });

    // Products API
    Route::apiResource('products', ApiProductController::class);
    
    // Orders API
    Route::apiResource('orders', ApiOrderController::class);
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->name('api.orders.status');
    
    // Order Items API
    Route::prefix('orders/{order}/items')->group(function () {
        Route::post('/', [OrderItemController::class, 'addItem'])
            ->name('api.orders.items.add');
        Route::put('/{item}', [OrderItemController::class, 'updateItem'])
            ->name('api.orders.items.update');
        Route::delete('/{item}', [OrderItemController::class, 'removeItem'])
            ->name('api.orders.items.remove');
    });
});
