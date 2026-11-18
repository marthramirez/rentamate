<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomAssignmentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('auth.api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/account/password', [AuthController::class, 'updatePassword']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('rooms', RoomController::class);
    Route::apiResource('tenants', TenantController::class);

    Route::apiResource('leases', LeaseController::class);
    Route::post('/leases/{lease}/allocate', [LeaseController::class, 'allocate']);
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate']);
    Route::post('/leases/{lease}/expire', [LeaseController::class, 'expire']);
    Route::post('/leases/{lease}/cancel', [LeaseController::class, 'cancel']);

    Route::get('/room-assignments', [RoomAssignmentController::class, 'index']);
    Route::get('/room-assignments/{roomAssignment}', [RoomAssignmentController::class, 'show']);
    Route::post('/room-assignments/{roomAssignment}/move-out', [RoomAssignmentController::class, 'moveOut']);
    Route::post('/room-assignments/{roomAssignment}/transfer', [RoomAssignmentController::class, 'transfer']);

    Route::get('/billing-records', [BillingRecordController::class, 'index']);
    Route::post('/billing-records', [BillingRecordController::class, 'store']);
    Route::get('/billing-records/{billingRecord}', [BillingRecordController::class, 'show']);
    Route::post('/billing-records/{billingRecord}/cancel', [BillingRecordController::class, 'cancel']);
    Route::post('/billing-records/{billingRecord}/payments', [PaymentController::class, 'store']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void']);
});
