<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\PermissionController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Tighter than the shared api limiter: these issue codes to a single phone
// and accept a six-digit secret.
Route::middleware('throttle:otp')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('permissions', PermissionController::class);
        Route::apiResource('roles', RoleController::class);
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

        Route::get('/users', [UserRoleController::class, 'index']);
        Route::post('/users/{user}/roles', [UserRoleController::class, 'syncRoles']);

        Route::get('/configs/otp', [ConfigController::class, 'showOtpConfig']);
        Route::put('/configs/otp', [ConfigController::class, 'updateOtpConfig']);
    });
});

