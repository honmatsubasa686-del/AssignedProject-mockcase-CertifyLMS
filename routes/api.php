<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);

        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });
