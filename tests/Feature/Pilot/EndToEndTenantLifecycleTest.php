<?php

namespace Tests\Feature\Pilot;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Attendance\Services\TimetableConflictService;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\ExamManagementService;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Exam\Services\ResultTabulationService;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\JournalEntryLine;
use App\Modules\Finance\Services\CashierSessionService;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Services\StudentAdmissionService;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Services\QuotaEnforcementService;
use App\Modules\SuperAdmin\Services\SubscriptionBillingService;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndTenantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_cross_module_school_tenant_pilot_lifecycle(): void
    {
        // =========================================================================
        // 1. SUPER ADMIN & SAAS SUBSCRIPTION PROVISIONING (Phase 1 & Phase 6)
        // =========================================================================
        (new SubscriptionPlanService())->seedDefaultPlans();
        $growthPlan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

        $superAdmin = User::factory()->create(['name' => 'Super Admin']);
        $staff = User::factory()->create(['name' => 'Campus Staff']);

        $tenant = Tenant::create([
            'name' => 'Pak-Turk Maarif Pilot Campus',
            'slug' => 'pak-turk-pilot',
            'type' => 'school',
            'status' => 'trial',
        ]);

        $billingService = new SubscriptionBillingService();
        $sub = $billingService->subscribeTenant($tenant, $growthPlan, true, 14, $superAdmin);
        $saasInvoice = $billingService->generateInvoice($sub);
        $billingService->recordPayment($saasInvoice, '1bill', (float) $saasInvoice->total_amount_pkr, '1BILL-PILOT-01', $superAdmin);

        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);

        $quotaService = new QuotaEnforcementService();
        $quotaService->assertCanCreateBranch($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Chak Shahzad Campus',
            'code' => 'PTM-CS',
            'city' => 'Islamabad',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($tenant);
        app(TenantContext::class)->setBranch($branch);

        // =========================================================================
        // 2. ACADEMIC SETUP & PII-ENCRYPTED STUDENT ADMISSION (Phase 2)
        // =========================================================================
        $session = AcademicSession::create([
            'tenant_id' => $tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $class9 = SchoolClass::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
            'display_order' => 9,
        ]);

        $sectionA = SchoolSection::create([
            'tenant_id' => $tenant->id,
            'school_class_id' => $class9->id,
            'name' => 'Jinnah',
            'capacity' => 40,
        ]);

        $subjectMath = Subject::create([
            'tenant_id' => $tenant->id,
            'name' => 'Mathematics',
            'code' => 'MATH-9',
            'type' => 'theory',
        ]);

        $quotaService->assertCanAdmitStudent($tenant);
        $admissionResult = (new StudentAdmissionService())->admitToSchool([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $sectionA->id,
            'first_name' => 'Zainab',
            'last_name' => 'Qureshi',
            'gender' => 'female',
            'date_of_birth' => '2012-08-14',
            'b_form' => '35202-1122334-2',
            'guardian_name' => 'Tariq Qureshi',
            'guardian_phone' => '+923009876543',
            'guardian_cnic' => '35202-9988776-1',
        ]);

        $student = $admissionResult['student'];
        $this->assertEquals('35202-1122334-2', $student->b_form);

        // =========================================================================
        // 3. TIMETABLE & ATTENDANCE ENGINE (Phase 4)
        // =========================================================================
        $room = CampusRoom::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'room_code' => 'R-201',
            'name' => 'Grade 9 Jinnah Hall',
            'capacity' => 40,
        ]);

        $period1 = SchoolPeriod::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
        ]);

        (new TimetableConflictService())->assignSchoolSlot([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $sectionA->id,
            'school_period_id' => $period1->id,
            'subject_id' => $subjectMath->id,
            'teacher_id' => $staff->id,
            'campus_room_id' => $room->id,
            'day_of_week' => 1,
        ]);

        $attService = new SchoolAttendanceService();
        $attSession = $attService->openOrFetchSession($tenant->id, $branch->id, $session->id, $sectionA->id, '2026-10-05', 'daily', null, $staff);
        $attService->markBulkAttendance($attSession, [
            $student->id => ['status' => 'present'],
        ], true, $staff);

        // =========================================================================
        // 4. FINANCIAL LEDGER, INVOICING & BLIND CASHIER COLLECTION (Phase 3)
        // =========================================================================
        $glService = new GeneralLedgerService();
        $glService->seedDefaultChartOfAccounts($tenant->id);
        $tuitionRev = ChartOfAccount::where('tenant_id', $tenant->id)->where('account_code', '4010')->firstOrFail();

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $tenant->id,
            'code' => 'TUITION',
            'name' => 'Monthly Tuition',
            'default_revenue_account_id' => $tuitionRev->id,
            'is_active' => true,
        ]);

        (new FeeStructureService())->createStructure([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_class_id' => $class9->id,
            'name' => 'Grade 9 Monthly Fee',
            'currency' => 'PKR',
            'is_active' => true,
        ], [
            ['fee_category_id' => $tuitionCat->id, 'amount' => 15000.00],
        ]);

        $invoice = (new InvoiceGenerationService())->generateSchoolMonthlyInvoice(
            $student,
            '2026-10',
            '2026-10-01',
            '2026-10-15',
            $session->id,
            $class9->id,
            $staff
        );
        $this->assertEquals(15000.00, (float) $invoice->total_amount);

        $cashierService = new CashierSessionService();
        $cashShift = $cashierService->openSession($staff, $branch, 2000.00);

        $payment = (new PaymentProcessingService())->processPayment($student, 15000.00, [
            'payment_method' => 'cash',
            'received_by_user_id' => $staff->id,
            'cashier_session_id' => $cashShift->id,
            'target_invoice_ids' => [$invoice->id],
        ]);
        $this->assertEquals(15000.00, (float) $payment->allocated_to_invoices);

        $closedShift = $cashierService->closeSessionBlind($cashShift, 17000.00, 'Balanced EOD');
        $this->assertEquals(0.00, (float) $closedShift->variance_amount);
        $cashierService->verifyAndPostClosing($closedShift, $superAdmin);

        // Verify double-entry equilibrium across entire tenant ledger
        $debits = (float) JournalEntryLine::where('tenant_id', $tenant->id)->where('entry_type', 'debit')->sum('amount');
        $credits = (float) JournalEntryLine::where('tenant_id', $tenant->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals($debits, $credits);
        $this->assertGreaterThan(0, $debits);

        // =========================================================================
        // 5. EXAMINATIONS, TABULATION & BILINGUAL REPORT CARD (Phase 5)
        // =========================================================================
        $scale = (new GradingScaleService())->seedDefaultFbiseScale($tenant->id);
        $term = ExamTerm::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Midterm 2026',
            'term_type' => 'midterm',
            'weightage_percentage' => 100.00,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-25',
            'status' => 'marks_entry',
        ]);

        $paper = ExamPaper::create([
            'tenant_id' => $tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $class9->id,
            'subject_id' => $subjectMath->id,
            'exam_date' => '2026-10-21',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);

        (new ExamManagementService())->recordBulkMarks($paper, $sectionA->id, [
            $student->id => ['marks' => 93.00],
        ], true, $staff);

        $results = (new ResultTabulationService())->tabulateSectionResults($term, $sectionA, true);
        $zainabResult = $results->firstOrFail();

        $this->assertEquals('A+', $zainabResult->grade_letter);
        $this->assertEquals(1, $zainabResult->section_rank);
        $this->assertEquals('pass', $zainabResult->result_status);
    }
}
