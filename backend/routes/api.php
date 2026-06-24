<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes  (prefix "api/v1" configured in bootstrap/app.php)
|--------------------------------------------------------------------------
| See docs/07-API.md for the full endpoint catalogue. Routes are added per
| domain module as each phase lands.
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register-company', [AuthController::class, 'registerCompany']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:api', 'tenant'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('me/entitlements', [AuthController::class, 'entitlements']);

    // Example of subscription-gated module routes (handlers land in Phase 3/4):
    // Route::middleware('module:payroll')->prefix('payroll')->group(...);
    // Route::middleware(['permission:payroll.run.execute','module:payroll'])->post('payroll/runs', ...);
});
