<?php

use App\Core\Printing\PdfReportService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Auth\LoginController;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\SuperAdmin\Controllers\SuperAdminDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Main Domain / Subdomain Router
Route::get('/', function () {
    $tenant = app(TenantContext::class)->getTenant();

    if ($tenant) {
        $currentBranch = app(TenantContext::class)->getBranch();
        return view('tenant.dashboard', compact('tenant', 'currentBranch'));
    }

    return redirect()->route('super-admin.dashboard');
})->name('home');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest')->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Institute Onboarding Wizard
Route::get('/onboarding', function () {
    return view('onboarding.index');
})->name('onboarding.index');

// Super Admin Platform Control Plane
Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'permission:platform.manage_tenants'])
    ->group(function () {
        Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/tenants/{tenant}/toggle-status', [SuperAdminDashboardController::class, 'toggleStatus'])->name('tenants.toggle-status');
        Route::view('/plans', 'super-admin.plans')->name('plans');
        Route::view('/billing', 'super-admin.billing')->name('billing');
    });

// Tenant Student Management
Route::prefix('students')
    ->name('tenant.students.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.students.index', compact('tenant'));
        })->middleware('permission:students.view')->name('index');

        Route::get('/create', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.students.create', compact('tenant'));
        })->middleware('permission:students.create')->name('create');
    });

// Tenant Financial Management
Route::prefix('finance')
    ->name('tenant.finance.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/terminal', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.terminal', compact('tenant'));
        })->middleware('permission:finance.collect_fee')->name('terminal');

        Route::get('/shifts', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.shifts', compact('tenant'));
        })->middleware('permission:finance.cashier_closing')->name('shifts');

        Route::get('/fee-structures', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.fee-structures', compact('tenant'));
        })->middleware('permission:finance.manage_structures')->name('fee-structures');

        Route::get('/vouchers', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.vouchers', compact('tenant'));
        })->middleware('permission:finance.manage_structures')->name('vouchers');

        Route::get('/vouchers/{invoice}/print', function (Request $request, FeeInvoice $invoice, PdfReportService $pdfService) {
            return $pdfService->renderFeeVoucher($invoice, $request->query('format') === 'pdf');
        })->middleware('permission:finance.view_vouchers')->name('voucher-print');

        Route::get('/payments/{payment}/receipt', function (Request $request, FeePayment $payment, PdfReportService $pdfService) {
            return $pdfService->renderThermalReceipt($payment, $request->query('format') === 'pdf');
        })->middleware('permission:finance.collect_fee')->name('receipt-print');

        Route::get('/ledger', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.ledger', compact('tenant'));
        })->middleware('permission:finance.view_vouchers')->name('ledger');
    });

// Tenant Attendance & Timetable Management
Route::prefix('attendance')
    ->name('tenant.attendance.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/timetable', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.attendance.timetable', compact('tenant'));
        })->middleware('permission:attendance.mark')->name('timetable');

        Route::get('/register', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.attendance.register', compact('tenant'));
        })->middleware('permission:attendance.mark')->name('register');

        Route::get('/leaves', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.attendance.leaves', compact('tenant'));
        })->middleware('permission:attendance.view')->name('leaves');

        Route::get('/analytics', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.attendance.analytics', compact('tenant'));
        })->middleware('permission:attendance.view')->name('analytics');
    });

// Tenant Examinations & Report Cards
Route::prefix('exams')
    ->name('tenant.exams.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/schedules', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.exam.schedules', compact('tenant'));
        })->middleware('permission:exams.manage')->name('schedules');

        Route::get('/marks', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.exam.marks', compact('tenant'));
        })->middleware('permission:exams.enter_marks')->name('marks');

        Route::get('/results', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.exam.results', compact('tenant'));
        })->middleware('permission:reports.view')->name('results');

        Route::get('/report-card/{result}', function (Request $request, ExamResult $result, PdfReportService $pdfService) {
            return $pdfService->renderExamReportCard($result, $request->query('format') === 'pdf');
        })->middleware('permission:reports.view')->name('report-card');
    });
