<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\CasteController;
use App\Http\Controllers\Admin\EmployeeFieldController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\ContactQueryController;
use App\Http\Controllers\Admin\JoinRequestController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\UserController;

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
            Route::resource('castes', CasteController::class);
        });

        // Employee Field Configuration
        Route::middleware('permission:employee_fields.manage')->group(function () {
            Route::resource('employee-fields', EmployeeFieldController::class);
        });

        // Employee Management
        // The write group is registered first so "employees/create" is matched
        // before the "employees/{employee}" show route can swallow it.
        Route::middleware('permission:employees.manage')->group(function () {
            Route::resource('employees', EmployeeController::class)->except(['index', 'show']);
            Route::patch('employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
            Route::patch('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        });
        Route::middleware('permission:employees.view,employees.manage')->group(function () {
            Route::resource('employees', EmployeeController::class)->only(['index', 'show']);
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
            Route::resource('contracts', ContractController::class)->except(['index', 'show']);
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

        // Employee Payouts
        Route::middleware('permission:payouts.manage')->group(function () {
            Route::resource('payouts', PayoutController::class)->except(['index', 'show']);
            Route::post('payouts/{payout}/mark-paid', [PayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        });
        Route::middleware('permission:payouts.view,payouts.manage')->group(function () {
            Route::resource('payouts', PayoutController::class)->only(['index', 'show']);
        });

        // Reports
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/patients', [ReportController::class, 'patients'])->name('reports.patients');
            Route::get('/reports/employees', [ReportController::class, 'employees'])->name('reports.employees');
            Route::get('/reports/assignments', [ReportController::class, 'assignments'])->name('reports.assignments');
            Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::post('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        });

        // CMS - Banners, Services, Blogs
        Route::middleware('permission:cms.manage')->group(function () {
            Route::resource('banners', BannerController::class);
            Route::patch('banners/{banner}/toggle-status', [BannerController::class, 'toggleStatus'])->name('banners.toggle-status');

            Route::resource('services', ServiceController::class);

            Route::resource('blogs', BlogController::class);
        });

        // Contact Queries + Join Requests
        Route::middleware('permission:queries.manage')->group(function () {
            Route::resource('contact-queries', ContactQueryController::class)->only(['index', 'show', 'update']);
            Route::post('contact-queries/{query}/respond', [ContactQueryController::class, 'respond'])->name('contact-queries.respond');

            Route::resource('join-requests', JoinRequestController::class)->only(['index', 'show', 'update']);
            Route::post('join-requests/{request}/approve', [JoinRequestController::class, 'approve'])->name('join-requests.approve');
            Route::post('join-requests/{request}/reject', [JoinRequestController::class, 'reject'])->name('join-requests.reject');
            Route::post('join-requests/{request}/convert', [JoinRequestController::class, 'convertToEmployee'])->name('join-requests.convert');
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
Route::post('/api/razorpay/callback', [PaymentController::class, 'callback'])->name('razorpay.callback');

