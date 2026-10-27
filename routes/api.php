<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\NeedsController;
use App\Http\Controllers\Api\PecsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('devices', [AuthController::class, 'registerDevice']);

        Route::get('children', [ChildController::class, 'index']);
        Route::post('children', [ChildController::class, 'store']);

        Route::post('child/alert', [NeedsController::class, 'alert'])->middleware('throttle:30,1');
        Route::get('children/{child}/needs', [NeedsController::class, 'feed']);
        Route::post('needs/{log}/acknowledge', [NeedsController::class, 'acknowledge']);
        Route::get('aac-cards', [NeedsController::class, 'cards']);
        Route::get('pecs.pdf', [PecsController::class, 'download']);

        Route::get('children/{child}/family', [FamilyMemberController::class, 'index']);
        Route::post('children/{child}/family', [FamilyMemberController::class, 'store']);
        Route::post('children/{child}/sessions', [SessionController::class, 'store']);

        Route::post('children/{child}/reports', [ReportController::class, 'generate']);
        Route::get('children/{child}/reports/latest', [ReportController::class, 'latest']);
    });
});
