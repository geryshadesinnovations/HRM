<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes  (prefix "api/v1" configured in bootstrap/app.php)
|--------------------------------------------------------------------------
| See docs/07-API.md for the full endpoint catalogue.
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register-company', [AuthController::class, 'registerCompany']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Public, signature-verified gateway webhooks (no auth / no tenant).
Route::post('webhooks/{gateway}', [WebhookController::class, 'handle']);

Route::middleware(['auth:api', 'tenant'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('me/entitlements', [AuthController::class, 'entitlements']);

    // --- Notifications (per-user, all roles) ---
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // --- Core HR (Employees, Departments, Designations) ---
    Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:employee.profile.view');
    Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:employee.profile.create');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employee.profile.view');
    Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employee.profile.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.profile.delete');

    Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:employee.profile.view');
    Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:company.settings.manage');
    Route::match(['put', 'patch'], 'departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:company.settings.manage');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:company.settings.manage');
    Route::get('designations', [DepartmentController::class, 'designations'])->middleware('permission:employee.profile.view');
    Route::post('designations', [DepartmentController::class, 'storeDesignation'])->middleware('permission:company.settings.manage');

    // --- Attendance (module-gated) ---
    Route::middleware('module:attendance')->prefix('attendance')->group(function (): void {
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:attendance.self');
        Route::post('check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:attendance.self');
        Route::post('mark', [AttendanceController::class, 'mark'])->middleware('permission:attendance.mark');
        Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:attendance.view');
    });

    // --- Leave (module-gated) ---
    Route::middleware('module:leave')->prefix('leave')->group(function (): void {
        Route::get('types', [LeaveController::class, 'types'])->middleware('permission:leave.view');
        Route::post('types', [LeaveController::class, 'storeType'])->middleware('permission:leave.type.manage');
        Route::get('balances', [LeaveController::class, 'balances'])->middleware('permission:leave.view');
        Route::get('requests', [LeaveController::class, 'requests'])->middleware('permission:leave.view');
        Route::post('requests', [LeaveController::class, 'store'])->middleware('permission:leave.request.create');
        Route::post('requests/{leaveRequest}/approve', [LeaveController::class, 'approve'])->middleware('permission:leave.request.approve');
        Route::post('requests/{leaveRequest}/reject', [LeaveController::class, 'reject'])->middleware('permission:leave.request.approve');
        Route::get('calendar', [LeaveController::class, 'calendar'])->middleware('permission:leave.view');
    });

    // --- Payroll (module-gated) ---
    Route::middleware('module:payroll')->prefix('payroll')->group(function (): void {
        Route::get('components', [PayrollController::class, 'components'])->middleware('permission:payroll.structure.manage');
        Route::post('components', [PayrollController::class, 'storeComponent'])->middleware('permission:payroll.structure.manage');
        Route::get('structures/{employee}', [PayrollController::class, 'showStructure'])->middleware('permission:payroll.structure.manage');
        Route::put('structures/{employee}', [PayrollController::class, 'setStructure'])->middleware('permission:payroll.structure.manage');
        Route::post('runs', [PayrollController::class, 'createRun'])->middleware('permission:payroll.run.execute');
        Route::post('runs/{run}/process', [PayrollController::class, 'process'])->middleware('permission:payroll.run.execute');
        Route::post('runs/{run}/publish', [PayrollController::class, 'publish'])->middleware('permission:payroll.run.execute');
        Route::get('runs/{run}/payslips', [PayrollController::class, 'payslips'])->middleware('permission:payroll.payslip.view.any');
    });
    Route::get('payslips/{payslip}', [PayrollController::class, 'showPayslip'])
        ->middleware(['module:payroll', 'permission:payroll.payslip.view.own']);

    // --- Reports (JSON, or CSV download with ?format=csv) ---
    Route::get('reports/employees', [ReportController::class, 'employees'])->middleware('permission:employee.profile.view');
    Route::get('reports/attendance-summary', [ReportController::class, 'attendanceSummary'])->middleware(['module:attendance', 'permission:attendance.report.view']);
    Route::get('reports/leave-balances', [ReportController::class, 'leaveBalances'])->middleware(['module:leave', 'permission:leave.view']);
    Route::get('reports/payroll-register/{run}', [ReportController::class, 'payrollRegister'])->middleware(['module:payroll', 'permission:payroll.report.view']);

    // --- Subscription & Billing (company self-service) ---
    Route::get('plans', [SubscriptionController::class, 'plans']);
    Route::get('subscription', [SubscriptionController::class, 'show'])->middleware('permission:company.subscription.manage');
    Route::post('subscription/upgrade', [SubscriptionController::class, 'upgrade'])->middleware('permission:company.subscription.manage');
    Route::post('subscription/downgrade', [SubscriptionController::class, 'downgrade'])->middleware('permission:company.subscription.manage');
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:company.subscription.manage');
    Route::post('subscription/reactivate', [SubscriptionController::class, 'reactivate'])->middleware('permission:company.subscription.manage');
    Route::get('invoices', [SubscriptionController::class, 'invoices'])->middleware('permission:company.billing.manage');
    Route::get('invoices/{invoice}', [SubscriptionController::class, 'showInvoice'])->middleware('permission:company.billing.manage');
    Route::post('billing/checkout', [SubscriptionController::class, 'checkout'])->middleware('permission:company.billing.manage');
});
