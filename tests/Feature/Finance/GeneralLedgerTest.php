<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Finance\Exceptions\LockOrderViolationException;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FiscalYear;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalEntryLine;
use App\Modules\Finance\Services\FinancialLockManager;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected GeneralLedgerService $glService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Lahore Grammar Main Campus',
            'slug' => 'lgs-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg Branch',
            'code' => 'GB-01',
            'city' => 'Lahore',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        $this->glService = new GeneralLedgerService();
    }

    protected function tearDown(): void
    {
        FinancialLockManager::reset();
        parent::tearDown();
    }

    public function test_can_seed_default_chart_of_accounts(): void
    {
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $this->assertDatabaseHas('chart_of_accounts', [
            'tenant_id' => $this->tenant->id,
            'account_code' => '1010',
            'account_name' => 'Cash on Hand',
            'is_system_controlled' => false,
        ]);

        $this->assertDatabaseHas('chart_of_accounts', [
            'tenant_id' => $this->tenant->id,
            'account_code' => '2010',
            'account_name' => 'Unearned Tuition Revenue (School)',
            'subledger_type' => 'student_fee_advances',
            'is_system_controlled' => true,
        ]);

        $this->assertDatabaseHas('chart_of_accounts', [
            'tenant_id' => $this->tenant->id,
            'account_code' => '2025',
            'subledger_type' => 'student_credit_wallets',
            'is_system_controlled' => true,
        ]);
    }

    public function test_can_create_fiscal_year_and_accounting_periods(): void
    {
        $fy = FiscalYear::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $fy->id,
            'name' => 'FY 2026-2027',
            'status' => 'open',
        ]);

        $period = AccountingPeriod::create([
            'tenant_id' => $this->tenant->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('accounting_periods', [
            'id' => $period->id,
            'period_name' => 'July 2026',
            'period_number' => 1,
        ]);

        $this->assertTrue($period->isOpen());
    }

    public function test_can_post_balanced_journal_entry(): void
    {
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $entry = $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'description' => 'Tuition fee collection test',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 15000.00, 'memo' => 'Cash received'],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 15000.00, 'memo' => 'Tuition fee earned'],
            ]
        );

        $this->assertEquals('posted', $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertEquals(15000.00, $entry->totalDebits());
        $this->assertEquals(15000.00, $entry->totalCredits());
        $this->assertCount(2, $entry->lines);
    }

    public function test_unbalanced_entry_update_to_posted_throws_trigger_exception(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        // Create draft entry directly in database
        $entry = JournalEntry::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'entry_number' => 'JV-UNBAL-001',
            'entry_date' => '2026-10-01',
            'status' => 'draft',
            'source_type' => 'manual',
        ]);

        JournalEntryLine::create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'entry_type' => 'debit',
            'amount' => 10000.00,
            'line_number' => 1,
        ]);

        JournalEntryLine::create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'entry_type' => 'credit',
            'amount' => 8000.00, // Unbalanced by PKR 2,000
            'line_number' => 2,
        ]);

        // Attempt to update directly to posted - DB trigger must abort
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unbalanced');

        DB::table('journal_entries')->where('id', $entry->id)->update(['status' => 'posted']);
    }

    public function test_deleting_or_mutating_posted_journal_entry_header_throws_trigger_exception(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $entry = $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 5000.00],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 5000.00],
            ]
        );

        // Attempting to delete posted entry must fail at DB trigger level
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Cannot delete posted journal entry');

        DB::table('journal_entries')->where('id', $entry->id)->delete();
    }

    public function test_deleting_or_mutating_posted_journal_entry_line_throws_trigger_exception(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $entry = $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 5000.00],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 5000.00],
            ]
        );

        $line = $entry->lines->first();

        // Attempt to delete line of posted entry must fail at DB trigger level
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Cannot DELETE line on journal entry');

        DB::table('journal_entry_lines')->where('id', $line->id)->delete();
    }

    public function test_bidirectional_reversal_creates_exact_mirrored_inverted_lines_and_links_entries(): void
    {
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $originalEntry = $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'description' => 'Original fee payment',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 8000.00, 'memo' => 'Original Cash Debit'],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 8000.00, 'memo' => 'Original Revenue Credit'],
            ]
        );

        // Execute formal reversal
        $reversalEntry = $this->glService->reverseEntry($originalEntry, 'Guardian cheque bounced');

        $originalEntry->refresh();
        $this->assertEquals('reversed', $originalEntry->status);
        $this->assertEquals($reversalEntry->id, $originalEntry->reversed_by_entry_id);

        $this->assertEquals('posted', $reversalEntry->status);
        $this->assertEquals($originalEntry->id, $reversalEntry->reverses_entry_id);
        $this->assertEquals('reversal', $reversalEntry->source_type);

        // Verify mirrored lines
        $reversalLines = $reversalEntry->lines;
        $this->assertCount(2, $reversalLines);

        $revCashLine = $reversalLines->firstWhere('account_id', $cashAccount->id);
        $this->assertNotNull($revCashLine);
        $this->assertEquals('credit', $revCashLine->entry_type); // Was debit, now credit!
        $this->assertEquals(8000.00, (float) $revCashLine->amount);

        $revRevLine = $reversalLines->firstWhere('account_id', $revenueAccount->id);
        $this->assertNotNull($revRevLine);
        $this->assertEquals('debit', $revRevLine->entry_type); // Was credit, now debit!
        $this->assertEquals(8000.00, (float) $revRevLine->amount);

        // Verify trial balance net balance is 0.00
        $tb = $this->glService->getTrialBalance($this->tenant->id);
        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(16000.00, $tb['total_debit']);
        $this->assertEquals(16000.00, $tb['total_credit']);
    }

    public function test_reversing_a_reversal_is_strictly_blocked(): void
    {
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $originalEntry = $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 5000.00],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 5000.00],
            ]
        );

        $reversalEntry = $this->glService->reverseEntry($originalEntry, 'First reversal');

        // Attempting to reverse the reversal entry must fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('It is itself a reversal entry');

        $this->glService->reverseEntry($reversalEntry, 'Illegal second reversal');
    }

    public function test_manual_journal_entry_targeting_system_control_account_throws_trigger_exception(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $unearnedTuition = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '2010')->firstOrFail();
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();

        $draftEntry = JournalEntry::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'entry_number' => 'JV-MANUAL-BAD-01',
            'entry_date' => '2026-10-01',
            'status' => 'draft',
            'source_type' => 'manual', // Prohibited source for 2010
        ]);

        // Attempting to insert a manual line targeting Account 2010 must be blocked by DB trigger
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('system-controlled sub-ledger account');

        JournalEntryLine::create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $draftEntry->id,
            'account_id' => $unearnedTuition->id,
            'entry_type' => 'credit',
            'amount' => 5000.00,
            'line_number' => 1,
        ]);
    }

    public function test_financial_lock_manager_enforces_monotonic_stages_and_throws_on_violation(): void
    {
        FinancialLockManager::reset();

        // Acquire Stage 3 (Invoice)
        FinancialLockManager::enterStage(FinancialLockManager::STAGE_INVOICE, 'testing invoice lock');
        $this->assertEquals(FinancialLockManager::STAGE_INVOICE, FinancialLockManager::getCurrentStage());

        // Attempting to go backwards to Stage 2 (Student) must throw LockOrderViolationException
        $this->expectException(LockOrderViolationException::class);
        $this->expectExceptionMessage('Lock order violation');

        FinancialLockManager::enterStage(FinancialLockManager::STAGE_STUDENT, 'illegal backwards lock');
    }

    public function test_trial_balance_computes_balanced_debits_and_credits(): void
    {
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();
        $labAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4040')->firstOrFail();

        $this->glService->postEntry(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-10-01',
                'source_type' => 'manual',
            ],
            [
                ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => 12000.00],
                ['account_id' => $revenueAccount->id, 'entry_type' => 'credit', 'amount' => 10000.00],
                ['account_id' => $labAccount->id, 'entry_type' => 'credit', 'amount' => 2000.00],
            ]
        );

        $tb = $this->glService->getTrialBalance($this->tenant->id);

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(12000.00, $tb['total_debit']);
        $this->assertEquals(12000.00, $tb['total_credit']);
        $this->assertNotEmpty($tb['accounts']);
    }

    public function test_immediate_rls_isolation_on_general_ledger_tables(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS test requires PostgreSQL.');
        }

        $tenantB = Tenant::create([
            'name' => 'Beaconhouse Defence Campus',
            'slug' => 'bhs-defence',
            'type' => 'school',
            'status' => 'active',
        ]);

        $branchB = Branch::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Defence Branch',
            'code' => 'BHS-01',
            'city' => 'Lahore',
        ]);

        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);
        $this->glService->seedDefaultChartOfAccounts($tenantB->id);

        $cashA = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();
        $revA = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();

        $cashB = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('account_code', '1010')->firstOrFail();
        $revB = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('account_code', '4010')->firstOrFail();

        // Create entries for both tenants
        $entryA = $this->glService->postEntry(
            ['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'entry_date' => '2026-10-01', 'source_type' => 'manual'],
            [['account_id' => $cashA->id, 'entry_type' => 'debit', 'amount' => 1000.00], ['account_id' => $revA->id, 'entry_type' => 'credit', 'amount' => 1000.00]]
        );

        $entryB = $this->glService->postEntry(
            ['tenant_id' => $tenantB->id, 'branch_id' => $branchB->id, 'entry_date' => '2026-10-01', 'source_type' => 'manual'],
            [['account_id' => $cashB->id, 'entry_type' => 'debit', 'amount' => 2000.00], ['account_id' => $revB->id, 'entry_type' => 'credit', 'amount' => 2000.00]]
        );

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visibleEntries = DB::table('journal_entries')->pluck('id')->toArray();

            // Tenant A can see their own entry, but Tenant B entry is completely invisible
            $this->assertContains($entryA->id, $visibleEntries);
            $this->assertNotContains($entryB->id, $visibleEntries);

            $visibleAccounts = DB::table('chart_of_accounts')->pluck('id')->toArray();
            $this->assertContains($cashA->id, $visibleAccounts);
            $this->assertNotContains($cashB->id, $visibleAccounts);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
