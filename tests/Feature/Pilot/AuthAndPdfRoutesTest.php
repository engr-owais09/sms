<?php

namespace Tests\Feature\Pilot;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\ExamManagementService;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Exam\Services\ResultTabulationService;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Services\StudentAdmissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndPdfRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_enforces_tenant_boundary_and_logout_works(): void
    {
        $this->withoutVite();
        (new RoleAndPermissionSeeder())->run();

        $provA = (new TenantProvisioningService())->provision([
            'name' => 'School A',
            'slug' => 'school-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@schoola.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Main A',
            'city' => 'Lahore',
        ]);
        Domain::firstOrCreate(
            ['domain' => 'school-a.localhost'],
            ['tenant_id' => $provA['tenant']->id, 'is_primary' => true, 'is_verified' => true]
        );

        $provB = (new TenantProvisioningService())->provision([
            'name' => 'School B',
            'slug' => 'school-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@schoolb.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Main B',
            'city' => 'Karachi',
        ]);
        Domain::firstOrCreate(
            ['domain' => 'school-b.localhost'],
            ['tenant_id' => $provB['tenant']->id, 'is_primary' => true, 'is_verified' => true]
        );

        // User from School A cannot log into School B subdomain
        $this->post('http://school-b.localhost/login', [
            'email' => 'ownera@schoola.test',
            'password' => 'Secret123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Owner A logs cleanly into School A subdomain
        $this->post('http://school-a.localhost/login', [
            'email' => 'ownera@schoola.test',
            'password' => 'Secret123!',
        ])->assertRedirect('http://school-a.localhost');
        $this->assertAuthenticatedAs($provA['owner']);

        // Super admin logs cleanly on central domain
        $this->post('http://school-a.localhost/logout')->assertRedirect('http://school-a.localhost/login');
        $this->assertGuest();

        $this->post('http://localhost/login', [
            'email' => 'superadmin@educore.test',
            'password' => 'Admin123456!',
        ])->assertRedirect('http://localhost/super-admin');
    }

    public function test_production_voucher_receipt_and_report_card_print_routes(): void
    {
        $this->withoutVite();

        $prov = (new TenantProvisioningService())->provision([
            'name' => 'Print Test Academy',
            'slug' => 'print-academy',
            'type' => 'school',
            'owner_name' => 'Print Admin',
            'owner_email' => 'admin@print.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Main Print',
            'city' => 'Lahore',
        ]);

        $tenant = $prov['tenant'];
        $branch = $prov['branch'];
        $owner = $prov['owner'];

        Domain::firstOrCreate(
            ['domain' => 'print.localhost'],
            ['tenant_id' => $tenant->id, 'is_primary' => true, 'is_verified' => true]
        );

        app(TenantContext::class)->setTenant($tenant);
        app(TenantContext::class)->setBranch($branch);

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

        $section = SchoolSection::create([
            'tenant_id' => $tenant->id,
            'school_class_id' => $class9->id,
            'name' => 'A',
            'capacity' => 30,
        ]);

        $subject = Subject::create([
            'tenant_id' => $tenant->id,
            'name' => 'Physics',
            'code' => 'PHY-9',
            'type' => 'theory',
        ]);

        $student = (new StudentAdmissionService())->admitToSchool([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $section->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Raza',
            'date_of_birth' => '2012-01-01',
        ])['student'];

        (new GeneralLedgerService())->seedDefaultChartOfAccounts($tenant->id);
        $rev = ChartOfAccount::where('tenant_id', $tenant->id)->where('account_code', '4010')->firstOrFail();
        $cat = FeeCategory::create([
            'tenant_id' => $tenant->id,
            'code' => 'TUITION',
            'name' => 'Monthly Tuition',
            'default_revenue_account_id' => $rev->id,
            'is_active' => true,
        ]);
        (new FeeStructureService())->createStructure([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_class_id' => $class9->id,
            'name' => 'Standard Fee',
            'currency' => 'PKR',
            'is_active' => true,
        ], [['fee_category_id' => $cat->id, 'amount' => 10000.00]]);

        $invoice = (new InvoiceGenerationService())->generateSchoolMonthlyInvoice(
            $student, '2026-11', '2026-11-01', '2026-11-15', $session->id, $class9->id, $owner
        );
        $payment = (new PaymentProcessingService())->processPayment($student, 10000.00, [
            'payment_method' => 'cash',
            'received_by_user_id' => $owner->id,
            'target_invoice_ids' => [$invoice->id],
        ]);

        $scale = (new GradingScaleService())->seedDefaultFbiseScale($tenant->id);
        $term = ExamTerm::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Midterm',
            'term_type' => 'midterm',
            'weightage_percentage' => 100.00,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
        ]);
        $paper = ExamPaper::create([
            'tenant_id' => $tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $class9->id,
            'subject_id' => $subject->id,
            'exam_date' => '2026-11-02',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);
        (new ExamManagementService())->recordBulkMarks($paper, $section->id, [$student->id => ['marks' => 91.00]], true, $owner);
        $result = (new ResultTabulationService())->tabulateSectionResults($term, $section, true)->firstOrFail();

        // Hit all 3 print routes as authenticated owner
        $this->actingAs($owner)
            ->get("http://print.localhost/finance/vouchers/{$invoice->id}/print")
            ->assertStatus(200)
            ->assertSee($invoice->invoice_number)
            ->assertSee('1Link / 1Bill PSID');

        $this->actingAs($owner)
            ->get("http://print.localhost/finance/payments/{$payment->id}/receipt")
            ->assertStatus(200)
            ->assertSee($payment->receipt_number)
            ->assertSee('10,000.00');

        $this->actingAs($owner)
            ->get("http://print.localhost/exams/report-card/{$result->id}")
            ->assertStatus(200)
            ->assertSee('Ahmed Raza');
    }
}
