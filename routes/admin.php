<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\CasteController;
use App\Http\Controllers\Admin\ContactQueryController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeFieldController;
use App\Http\Controllers\Admin\EmployeeSalaryController;
use App\Http\Controllers\Admin\JoinRequestController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // Authentication Routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

        // Throttled per IP: the admin panel is the one unauthenticated write
        // endpoint on the session guard, so it is the natural target for
        // credential stuffing.
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:admin-login')
            ->name('login.post');

        // Second step for staff: the password is correct but the session is
        // not logged in until the owner-approval code is entered.
        Route::get('/login/otp', [AuthController::class, 'showOtp'])->name('login.otp');
        Route::post('/login/otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:admin-otp')
            ->name('login.otp.verify');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Protected Admin Routes
    Route::middleware(['auth', 'admin'])->group(function () {

        // Dashboard
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        });

        // Settings
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        });

        // User Management
        Route::middleware('permission:users.manage')->group(function () {
            Route::resource('users', UserController::class);
            Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        });

        // Caste Management
        Route::middleware('permission:castes.manage')->group(function () {
            Route::resource('castes', CasteController::class)->except(['show']);
        });

        // Employee Field Configuration
        Route::middleware('permission:employee_fields.manage')->group(function () {
            Route::resource('employee-fields', EmployeeFieldController::class)->except(['show']);
        });

        // Employee Management
        // The write group is registered first so "employees/create" is matched
        // before the "employees/{employee}" show route can swallow it.
        Route::middleware('permission:employees.manage')->group(function () {
            Route::resource('employees', EmployeeController::class)->except(['index', 'show', 'destroy']);
            Route::patch('employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
            Route::patch('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        });
        Route::middleware('permission:employees.view,employees.manage')->group(function () {
            Route::resource('employees', EmployeeController::class)->only(['index', 'show']);

            // Photos and KYC documents live on the private disk and are only
            // reachable through these authenticated routes.
            Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
            Route::get('employees/{employee}/documents/{index}', [EmployeeController::class, 'document'])
                ->whereNumber('index')
                ->name('employees.documents.show');
        });

        // Patient Management
        Route::middleware('permission:patients.manage')->group(function () {
            Route::resource('patients', PatientController::class)->except(['index', 'show']);
        });
        Route::middleware('permission:patients.view,patients.manage')->group(function () {
            Route::resource('patients', PatientController::class)->only(['index', 'show']);
        });

        // Contract Management
        Route::middleware('permission:contracts.manage')->group(function () {
            Route::resource('contracts', ContractController::class)->except(['index', 'show', 'destroy']);
        });
        Route::middleware('permission:contracts.view,contracts.manage')->group(function () {
            Route::resource('contracts', ContractController::class)->only(['index', 'show']);
        });
        Route::middleware('permission:contracts.view,contracts.manage')->group(function () {
            Route::get('contracts/{contract}/download', [ContractController::class, 'download'])->name('contracts.download');
            Route::get('contracts/{contract}/pdf', [ContractController::class, 'viewPdf'])->name('contracts.view-pdf');
            Route::get('contracts/{contract}/print', [ContractController::class, 'printPdf'])->name('contracts.print-pdf');
        });

        Route::middleware('permission:contracts.manage')->group(function () {
            Route::post('contracts/{contract}/close', [ContractController::class, 'close'])->name('contracts.close');
            Route::post('contracts/{contract}/regenerate-pdf', [ContractController::class, 'regeneratePdf'])->name('contracts.regenerate-pdf');
            Route::post('contracts/{contract}/employees', [ContractController::class, 'addEmployees'])->name('contracts.employees.add');
            Route::delete('contracts/{contract}/assignments/{assignment}', [ContractController::class, 'removeEmployee'])->name('contracts.assignments.remove');
            Route::post('contracts/{contract}/assignments/{assignment}/replace', [ContractController::class, 'replaceEmployee'])->name('contracts.assignments.replace');
            Route::post('contracts/{contract}/assignments/{assignment}/notes', [ContractController::class, 'addEmployeeNote'])->name('contracts.assignments.notes.store');
            Route::post('contracts/{contract}/assignments/{assignment}/cover', [ContractController::class, 'assignCover'])->name('contracts.assignments.cover');
            Route::post('contracts/{contract}/assignments/{assignment}/cover/end', [ContractController::class, 'endCover'])->name('contracts.assignments.cover.end');
        });

        // Contract Payment / Collection
        Route::middleware('permission:payments.view,payments.manage')->group(function () {
            Route::get('contracts/{contract}/payment', [ContractController::class, 'payment'])->name('contracts.payment');
        });
        Route::middleware('permission:payments.manage')->group(function () {
            Route::post('contracts/{contract}/payment', [ContractController::class, 'storePayment'])->name('contracts.payment.store');
            Route::post('contracts/{contract}/payment/razorpay/order', [ContractController::class, 'razorpayOrder'])->name('contracts.payment.razorpay-order');
            Route::post('contracts/{contract}/payment/razorpay/verify', [ContractController::class, 'razorpayVerify'])->name('contracts.payment.razorpay-verify');
        });

        // Attendance (employee operations)
        Route::middleware('permission:employees.manage')->group(function () {
            Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::post('attendance/bulk', [AttendanceController::class, 'bulk'])->name('attendance.bulk');
        });
        Route::middleware('permission:employees.view,employees.manage')->group(function () {
            Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('attendance/register', [AttendanceController::class, 'register'])->name('attendance.register');
        });

        // Salary & Payroll
        Route::middleware('permission:payouts.manage')->group(function () {
            Route::post('employees/{employee}/salaries', [EmployeeSalaryController::class, 'store'])->name('employees.salaries.store');
            Route::delete('employees/{employee}/salaries/{salary}', [EmployeeSalaryController::class, 'destroy'])->name('employees.salaries.destroy');

            Route::post('payouts/generate', [PayoutController::class, 'generate'])->name('payouts.generate');
            Route::post('payouts/{payout}/recalculate', [PayoutController::class, 'recalculate'])->name('payouts.recalculate');
            Route::post('payouts/{payout}/items', [PayoutController::class, 'storeItem'])->name('payouts.items.store');
            Route::delete('payouts/{payout}/items/{item}', [PayoutController::class, 'destroyItem'])->name('payouts.items.destroy');
            Route::post('payouts/{payout}/mark-paid', [PayoutController::class, 'markPaid'])->name('payouts.mark-paid');
            Route::delete('payouts/{payout}', [PayoutController::class, 'destroy'])->name('payouts.destroy');
        });
        Route::middleware('permission:payouts.view,payouts.manage')->group(function () {
            Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
            Route::get('payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
        });

        // Reports
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            // Each report also requires the permission that guards its source
            // data, so reports.view cannot be used to read around it.
            Route::get('/reports/patients', [ReportController::class, 'patients'])
                ->middleware('permission:patients.view,patients.manage')->name('reports.patients');
            Route::get('/reports/employees', [ReportController::class, 'employees'])
                ->middleware('permission:employees.view,employees.manage')->name('reports.employees');
            Route::get('/reports/contracts', [ReportController::class, 'contracts'])
                ->middleware('permission:contracts.view,contracts.manage')->name('reports.contracts');
            Route::get('/reports/assignments', [ReportController::class, 'assignments'])
                ->middleware('permission:contracts.view,contracts.manage')->name('reports.assignments');
            Route::get('/reports/payouts', [ReportController::class, 'payouts'])
                ->middleware('permission:payouts.view,payouts.manage')->name('reports.payouts');
            Route::get('/reports/revenue', [ReportController::class, 'revenue'])
                ->middleware('permission:payments.view,payments.manage')->name('reports.revenue');
            // export() checks the same per-type permission itself.
            Route::post('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        });

        // CMS - Banners, Services, Blogs
        Route::middleware('permission:cms.manage')->group(function () {
            Route::resource('banners', BannerController::class)->except(['show']);
            Route::patch('banners/{banner}/toggle-status', [BannerController::class, 'toggleStatus'])->name('banners.toggle-status');

            Route::resource('services', ServiceController::class)->except(['show']);

            Route::resource('blogs', BlogController::class)->except(['show']);
        });

        // Contact Queries + Join Requests
        Route::middleware('permission:queries.manage')->group(function () {
            Route::resource('contact-queries', ContactQueryController::class)->only(['index', 'show', 'update']);
            // Segment names must match the controller argument names, or
            // implicit binding skips them and injects an empty model.
            Route::post('contact-queries/{contactQuery}/respond', [ContactQueryController::class, 'respond'])->name('contact-queries.respond');

            Route::resource('join-requests', JoinRequestController::class)->only(['index', 'show', 'update']);
            Route::post('join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve'])->name('join-requests.approve');
            Route::post('join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject'])->name('join-requests.reject');
            Route::post('join-requests/{joinRequest}/convert', [JoinRequestController::class, 'convertToEmployee'])->name('join-requests.convert');
        });

        // Notifications
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
            Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        });

        // Payments
        Route::middleware('permission:payments.view,payments.manage')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Payment Webhook Routes
|--------------------------------------------------------------------------
*/

Route::post('/api/razorpay/webhook', [PaymentController::class, 'webhook'])->name('razorpay.webhook');
// Unauthenticated and CSRF-exempt, so cap how fast it can be probed.
Route::post('/api/razorpay/callback', [PaymentController::class, 'callback'])
    ->middleware('throttle:30,1')
    ->name('razorpay.callback');
