<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WebhookController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware([])->group(function () {
    //Stock
    Route::get('stock', [StockController::class, 'getStock']);
    Route::get('historic', [StockController::class, 'historicalAnalysis']);
    Route::post('webhook', [WebhookController::class, 'webhook']);

    Route::get('setWebhook', [WebhookController::class, 'setWebhook']);

});
