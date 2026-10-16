<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventIngestionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\OrderController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/v1/events', [EventIngestionController::class, 'store']);
    Route::post('/v1/orders', [OrderController::class, 'store']);
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/v1/analytics/overview', [
        AnalyticsController::class,
        'overview',
    ]);

    Route::get('/v1/analytics/trends', [
        AnalyticsController::class,
        'trends',
    ]);

    Route::get('/v1/analytics/pages', [
        AnalyticsController::class,
        'pages',
    ]);

    Route::get('/v1/analytics/recent-events', [
        AnalyticsController::class,
        'recentEvents',
    ]);
});
