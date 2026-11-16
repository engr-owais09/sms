<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Finance\Livewire\CashierShiftManager;
use App\Modules\Finance\Livewire\CashierTerminal;
use App\Modules\Finance\Livewire\FeeStructureManager;
use App\Modules\Finance\Livewire\GeneralLedgerExplorer;
use App\Modules\Finance\Livewire\VoucherGenerationConsole;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeInvoiceItem;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\FeeStructureItem;
use App\Modules\Finance\Models\FiscalYear;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalEntryLine;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceLivewireComponentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $user;
    protected Student $student;
    protected FiscalYear $fiscalYear;
    protected FeeCategory $tuitionCategory;
    protected ChartOfAccount $cashAccount;
    protected ChartOfAccount $arAccount;
    protected ChartOfAccount $tuitionRevAccount;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'pgsql') {
            PostgresTenantContext::reset();
        }

        $this->tenant = Tenant::create([
            'name' => 'Beaconhouse Lahore',
            'slug' => 'bhs-lhr-ui',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg Campus',
            'code' => 'BHS-G1',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Cashier Aslam',
            'email' => 'cashier.aslam@bhs.edu.pk',
        ]);

        $this->actingAs($this->user);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        if (DB::getDriverName() === 'pgsql') {
            PostgresTenantContext::set($this->tenant->id);
        }

        // Setup base chart of accounts using GeneralLedgerService
        $glService = app(GeneralLedgerService::class);
        $glService->seedDefaultChartOfAccounts($this->tenant->id);

        $this->cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $this->arAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1200')->firstOrFail();
        $this->tuitionRevAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $this->fiscalYear = FiscalYear::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY 2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'open',
        ]);

        $this->tuitionCategory = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Monthly Tuition',
            'default_revenue_account_id' => $this->tuitionRevAccount->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'BHS-UI-101',
            'first_name' => 'Hamza',
            'last_name' => 'Tariq',
            'date_of_birth' => '2012-05-10',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);
    }

    public function test_cashier_terminal_loads_student_invoices_and_processes_payment(): void
    {
        // 1. Create open cashier session
        $session = CashierSession::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'cashier_user_id' => $this->user->id,
            'session_code' => 'CS-UI-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_float' => 5000.00,
            'expected_cash_balance' => 5000.00,
        ]);

        // 2. Create pending fee invoice
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-UI-1001',
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'subtotal' => 12000.00,
            'total_amount' => 12000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
            'barcode_code128' => '990001112223334445',
        ]);

        FeeInvoiceItem::create([
            'tenant_id' => $this->tenant->id,
            'fee_invoice_id' => $invoice->id,
            'fee_category_id' => $this->tuitionCategory->id,
            'gross_amount' => 12000.00,
            'discount_amount' => 0.00,
            'net_amount' => 12000.00,
            'description' => 'Monthly Tuition',
            'sort_order' => 1,
        ]);

        // 3. Test Livewire component
        Livewire::test(CashierTerminal::class)
            ->set('searchQuery', 'BHS-UI-101')
            ->assertSet('selectedStudentId', $this->student->id)
            ->assertCount('invoices', 1)
            ->assertSet('amountTendered', 12000.00)
            ->set('paymentMethod', 'cash')
            ->call('processPayment')
            ->assertHasNoErrors()
            ->assertSee('Payment of PKR 12,000.00 processed successfully');

        // Verify DB update
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(12000.00, (float) $invoice->paid_amount);

        // Verify drawer expected balance incremented
        $session->refresh();
        $this->assertEquals(17000.00, (float) $session->expected_cash_balance);
    }

    public function test_cashier_shift_manager_opens_shift_and_performs_blind_close_with_denominations(): void
    {
        $testComponent = Livewire::test(CashierShiftManager::class)
            ->set('openingFloat', 10000.00)
            ->set('openingNotes', 'Morning shift float')
            ->call('openShift')
            ->assertHasNoErrors()
            ->assertSee('Cashier shift opened successfully');

        $activeSession = CashierSession::where('cashier_user_id', $this->user->id)
            ->where('status', 'open')
            ->first();

        $this->assertNotNull($activeSession);
        $this->assertEquals(10000.00, (float) $activeSession->opening_float);

        // Open blind close modal and enter denominations:
        // 2x 5000 = 10000, 3x 1000 = 3000, 2x 500 = 1000, coins = 250 -> Total = 14,250
        $testComponent
            ->call('openCloseModal')
            ->assertSet('showBlindCloseModal', true)
            ->set('note5000', 2)
            ->set('note1000', 3)
            ->set('note500', 2)
            ->set('coins', 250.00)
            ->set('closingNotes', 'End of day counted cash')
            ->call('submitBlindClose')
            ->assertHasNoErrors()
            ->assertSee('Shift closed successfully with declared cash PKR 14,250.00');

        $activeSession->refresh();
        $this->assertEquals('closed', $activeSession->status);
        $this->assertEquals(14250.00, (float) $activeSession->actual_counted_cash);
        $this->assertEquals(4250.00, (float) $activeSession->variance_amount);
        $this->assertEquals(2, $activeSession->denominations[5000]);

        // Supervisor verification
        $testComponent->call('verifySession', $activeSession->id)
            ->assertHasNoErrors()
            ->assertSee('verified and posted by supervisor successfully');

        $activeSession->refresh();
        $this->assertEquals('reconciled', $activeSession->status);
    }

    public function test_general_ledger_explorer_displays_balanced_trial_balance_and_exports_csv(): void
    {
        // Post a balanced journal entry
        $glService = app(GeneralLedgerService::class);
        $glService->postEntry([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'source_type' => 'manual',
            'entry_date' => '2026-10-01',
            'description' => 'Capital injection',
        ], [
            [
                'account_id' => $this->cashAccount->id,
                'entry_type' => 'debit',
                'amount' => 50000.00,
                'memo' => 'Debit Cash',
            ],
            [
                'account_id' => $this->tuitionRevAccount->id,
                'entry_type' => 'credit',
                'amount' => 50000.00,
                'memo' => 'Credit Tuition Revenue',
            ],
        ], $this->user);

        $test = Livewire::test(GeneralLedgerExplorer::class)
            ->assertSet('activeTab', 'trial_balance');

        $tbData = $test->viewData('tbData');
        $this->assertTrue($tbData['isBalanced']);
        $this->assertEquals(50000.00, $tbData['totalDebits']);
        $this->assertEquals(50000.00, $tbData['totalCredits']);

        // Test CSV export download
        $test->call('exportTrialBalanceCsv')->assertFileDownloaded();
    }

    public function test_defaulters_aging_buckets_unpaid_invoices_correctly(): void
    {
        // Create invoices with various overdue days
        // Invoice 1: Overdue by 15 days (current 0-30 bucket)
        FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-DEF-01',
            'billing_period' => '2026-09',
            'issue_date' => now()->subDays(30)->format('Y-m-d'),
            'due_date' => now()->subDays(15)->format('Y-m-d'),
            'total_amount' => 5000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        // Invoice 2: Overdue by 75 days (60-90 bucket)
        FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-DEF-02',
            'billing_period' => '2026-07',
            'issue_date' => now()->subDays(90)->format('Y-m-d'),
            'due_date' => now()->subDays(75)->format('Y-m-d'),
            'total_amount' => 8000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        $test = Livewire::test(GeneralLedgerExplorer::class)
            ->set('activeTab', 'defaulters');

        $defData = $test->viewData('defaultersData');
        $this->assertEquals(13000.00, $defData['summary']['total']);
        $this->assertEquals(5000.00, $defData['summary']['current']);
        $this->assertEquals(8000.00, $defData['summary']['60_90']);

        // Test Defaulters CSV export
        $test->call('exportDefaultersCsv')->assertFileDownloaded();
    }

    public function test_fee_structure_manager_creates_category_and_structure(): void
    {
        Livewire::test(FeeStructureManager::class)
            ->set('categoryCode', 'TRANSPORT')
            ->set('categoryName', 'Transport & Bus Service')
            ->set('categoryRevenueAccountId', $this->tuitionRevAccount->id)
            ->call('createCategory')
            ->assertHasNoErrors()
            ->assertSee('Fee Category created successfully');

        $this->assertDatabaseHas('fee_categories', [
            'tenant_id' => $this->tenant->id,
            'code' => 'TRANSPORT',
        ]);

        Livewire::test(FeeStructureManager::class)
            ->set('structureName', 'Standard High School Fee 2026')
            ->set('structureBranchId', $this->branch->id)
            ->set('structureItems', [
                [
                    'fee_category_id' => $this->tuitionCategory->id,
                    'amount' => 15000.00,
                    'is_optional' => false,
                ],
            ])
            ->call('createStructure')
            ->assertHasNoErrors()
            ->assertSee('Fee Structure created successfully');

        $this->assertDatabaseHas('fee_structures', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard High School Fee 2026',
        ]);
    }
}
