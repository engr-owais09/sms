<?php

use App\Core\Audit\Models\TenantAuditLog;
use App\Core\Printing\PdfReportService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Modules\Finance\Controllers\PaymentWebhookController;
use App\Modules\Institute\Controllers\BulkDataController;
use App\Modules\Institute\Controllers\PortalController;
use App\Modules\Institute\Controllers\TenantExportController;
use App\Modules\Student\Controllers\StudentDocumentController;
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

        if (auth()->check()) {
            return view('tenant.dashboard', compact('tenant', 'currentBranch'));
        }

        return view('landing.tenant', compact('tenant', 'currentBranch'));
    }

    if (auth()->check() && auth()->user()->isSuperAdmin()) {
        return redirect()->route('super-admin.dashboard');
    }

    $plans = \App\Modules\SuperAdmin\Models\SubscriptionPlan::query()
        ->where('is_active', true)
        ->orderBy('price_pkr')
        ->get();

    return view('landing.platform', compact('plans'));
})->name('home');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:30,1'])->name('login.post');
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->middleware('guest')->name('two-factor.login');
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware(['guest', 'throttle:15,1'])->name('two-factor.login.store');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/password', [AccountSecurityController::class, 'editPassword'])->name('password.edit');
    Route::post('/password', [AccountSecurityController::class, 'updatePassword'])->name('password.update');
    Route::post('/logout-other-devices', [AccountSecurityController::class, 'logoutOtherDevices'])->name('logout-other-devices');
    Route::post('/two-factor/enable', [AccountSecurityController::class, 'enableTwoFactor'])->name('two-factor.enable');
    Route::post('/two-factor/confirm', [AccountSecurityController::class, 'confirmTwoFactor'])->name('two-factor.confirm');
    Route::post('/two-factor/disable', [AccountSecurityController::class, 'disableTwoFactor'])->name('two-factor.disable');
});

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

// Tenant Academic Setup & Staff Management
Route::prefix('academics')
    ->name('tenant.academics.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/setup', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.academic.setup', compact('tenant'));
        })->middleware('permission:academics.manage')->name('setup');
    });

Route::prefix('settings')
    ->name('tenant.staff.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/staff', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.staff.index', compact('tenant'));
        })->middleware('permission:institute.edit_settings')->name('index');

        Route::get('/audit-logs', function () {
            $tenant = app(TenantContext::class)->getTenant();
            $logs = TenantAuditLog::with('actor')
                ->where('tenant_id', $tenant->id)
                ->latest('created_at')
                ->paginate(25);
            return view('tenant.staff.audit-logs', compact('tenant', 'logs'));
        })->middleware('permission:institute.edit_settings')->name('audit-logs');

        Route::post('/export', [TenantExportController::class, 'store'])
            ->middleware(['throttle:3,60', 'permission:institute.edit_settings'])
            ->name('export.store');

        Route::get('/export/download', [TenantExportController::class, 'download'])
            ->middleware(['signed', 'permission:institute.edit_settings'])
            ->name('export.download');
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

        Route::get('/import', [BulkDataController::class, 'showImport'])
            ->middleware('permission:students.create')->name('import');
        Route::get('/import/template', [BulkDataController::class, 'downloadTemplate'])
            ->middleware('permission:students.create')->name('import.template');
        Route::post('/import', [BulkDataController::class, 'importStudents'])
            ->middleware('permission:students.create')->name('import.store');
        Route::get('/export', [BulkDataController::class, 'exportStudents'])
            ->middleware('permission:students.view')->name('export');

        Route::get('/documents/{document}/download', [StudentDocumentController::class, 'download'])
            ->middleware(['signed', 'permission:students.view'])
            ->name('documents.download');
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

        Route::get('/operations', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.finance.operations', compact('tenant'));
        })->middleware('permission:finance.manage_structures')->name('operations');
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

        Route::get('/assessments', function () {
            $tenant = app(TenantContext::class)->getTenant();
            return view('tenant.exam.assessments', compact('tenant'));
        })->middleware('permission:exams.manage')->name('assessments');

        Route::get('/report-card/{result}', function (Request $request, ExamResult $result, PdfReportService $pdfService) {
            return $pdfService->renderExamReportCard($result, $request->query('format') === 'pdf');
        })->middleware('permission:reports.view')->name('report-card');
    });

// Portals & Notice Board (R5)
Route::prefix('portal')
    ->name('portal.')
    ->middleware(['tenant', 'auth'])
    ->group(function () {
        Route::get('/parent', [PortalController::class, 'parentDashboard'])
            ->middleware(['role:parent', 'throttle:60,1'])
            ->name('parent');
        Route::post('/parent/leave', [PortalController::class, 'submitParentLeave'])
            ->middleware(['role:parent', 'throttle:20,1'])
            ->name('parent.leave');

        Route::get('/student', [PortalController::class, 'studentDashboard'])
            ->middleware(['role:student', 'throttle:60,1'])
            ->name('student');

        Route::get('/teacher', [PortalController::class, 'teacherDashboard'])
            ->middleware(['role:teacher|institute_owner|principal', 'throttle:60,1'])
            ->name('teacher');
    });

Route::prefix('notices')
    ->name('tenant.notices.')
    ->middleware(['tenant', 'auth', 'permission:institute.view_settings|academics.manage'])
    ->group(function () {
        Route::get('/', [PortalController::class, 'noticesIndex'])->name('index');
        Route::post('/', [PortalController::class, 'publishNotice'])->name('store');
    });

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');
