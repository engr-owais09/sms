<?php

namespace App\Providers;

use App\Core\Tenancy\TenantContext;
use App\Modules\Attendance\Livewire\AttendanceAnalyticsReport;
use App\Modules\Attendance\Livewire\LeaveApprovalConsole;
use App\Modules\Attendance\Livewire\RollCallRegister;
use App\Modules\Attendance\Livewire\TimetableBuilder;
use App\Modules\Exam\Livewire\ExamScheduleManager;
use App\Modules\Exam\Livewire\MarksEntryConsole;
use App\Modules\Exam\Livewire\ResultTabulationPortal;
use App\Modules\Finance\Livewire\CashierShiftManager;
use App\Modules\Finance\Livewire\CashierTerminal;
use App\Modules\Finance\Livewire\FeeStructureManager;
use App\Modules\Finance\Livewire\GeneralLedgerExplorer;
use App\Modules\Finance\Livewire\VoucherGenerationConsole;
use App\Modules\SuperAdmin\Livewire\SubscriptionPlanManager;
use App\Modules\SuperAdmin\Livewire\TenantBillingConsole;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('finance.cashier-terminal', CashierTerminal::class);
        Livewire::component('finance.cashier-shift-manager', CashierShiftManager::class);
        Livewire::component('finance.general-ledger-explorer', GeneralLedgerExplorer::class);
        Livewire::component('finance.fee-structure-manager', FeeStructureManager::class);
        Livewire::component('finance.voucher-generation-console', VoucherGenerationConsole::class);

        Livewire::component('attendance.timetable-builder', TimetableBuilder::class);
        Livewire::component('attendance.roll-call-register', RollCallRegister::class);
        Livewire::component('attendance.leave-approval-console', LeaveApprovalConsole::class);
        Livewire::component('attendance.attendance-analytics-report', AttendanceAnalyticsReport::class);

        Livewire::component('exam.exam-schedule-manager', ExamScheduleManager::class);
        Livewire::component('exam.marks-entry-console', MarksEntryConsole::class);
        Livewire::component('exam.result-tabulation-portal', ResultTabulationPortal::class);

        Livewire::component('super-admin.subscription-plan-manager', SubscriptionPlanManager::class);
        Livewire::component('super-admin.tenant-billing-console', TenantBillingConsole::class);
    }
}
