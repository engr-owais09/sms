<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FiscalYear;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialRlsSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected array $all24FinancialTables = [
        'fiscal_years',
        'accounting_periods',
        'chart_of_accounts',
        'fee_categories',
        'fee_structures',
        'fee_structure_items',
        'fee_discounts',
        'student_fee_discounts',
        'installment_plans',
        'installment_milestones',
        'fee_invoices',
        'fee_invoice_items',
        'student_credit_wallets',
        'student_credit_transactions',
        'student_fee_advances',
        'student_fee_advance_allocations',
        'cashier_sessions',
        'fee_payments',
        'fee_payment_allocations',
        'fee_refunds',
        'fee_refund_allocations',
        'expense_vouchers',
        'journal_entries',
        'journal_entry_lines',
    ];

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Student $studentA;
    protected Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS security audit requires PostgreSQL.');
        }

        // Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Lahore Grammar School',
            'slug' => 'lgs-rls',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Gulberg Branch',
            'code' => 'LGS-G1',
            'city' => 'Lahore',
        ]);

        $this->studentA = Student::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'admission_number' => 'LGS-RLS-01',
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        // Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'Beaconhouse Defence',
            'slug' => 'bhs-rls',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Defence Branch',
            'code' => 'BHS-D1',
            'city' => 'Lahore',
        ]);

        $this->studentB = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'admission_number' => 'BHS-RLS-02',
            'first_name' => 'Bilal',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-02-02',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);
    }

    public function test_all_24_financial_tables_have_rls_enabled_and_forced_in_postgresql_catalog(): void
    {
        $records = DB::select("
            SELECT c.relname as table_name, c.relrowsecurity as rls_enabled, c.relforcerowsecurity as rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' 
              AND c.relkind = 'r'
              AND c.relname IN ('" . implode("', '", $this->all24FinancialTables) . "')
        ");

        $catalog = [];
        foreach ($records as $r) {
            $catalog[$r->table_name] = [
                'enabled' => (bool) $r->rls_enabled,
                'forced' => (bool) $r->rls_forced,
            ];
        }

        $this->assertCount(24, $this->all24FinancialTables);

        foreach ($this->all24FinancialTables as $table) {
            $this->assertArrayHasKey($table, $catalog, "Table {$table} is missing from PostgreSQL catalog.");
            $this->assertTrue($catalog[$table]['enabled'], "Table {$table} does not have ROW LEVEL SECURITY enabled.");
            $this->assertTrue($catalog[$table]['forced'], "Table {$table} does not have FORCE ROW LEVEL SECURITY enabled.");
        }
    }

    public function test_all_24_financial_tables_have_active_tenant_isolation_policy_for_educore_app(): void
    {
        $policies = DB::select("
            SELECT tablename, policyname, roles::text as roles
            FROM pg_policies
            WHERE schemaname = 'public'
              AND tablename IN ('" . implode("', '", $this->all24FinancialTables) . "')
        ");

        $foundTables = array_unique(array_map(fn($p) => $p->tablename, $policies));

        foreach ($this->all24FinancialTables as $table) {
            $this->assertContains($table, $foundTables, "Table {$table} is missing an active RLS policy.");
        }
    }

    public function test_fail_closed_behavior_when_tenant_context_is_unset(): void
    {
        // Insert sample records as privileged user
        FiscalYear::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'FY 2026-27 A',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        FeeCategory::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'TUITION_A',
            'name' => 'Tuition Fee A',
        ]);

        FeeInvoice::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'student_id' => $this->studentA->id,
            'invoice_number' => 'INV-FAILCLOSED-01',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 5000.00,
        ]);

        // Switch to unprivileged role educore_app with NO tenant context set
        DB::statement('SET ROLE educore_app');
        DB::statement("RESET app.current_tenant_id");

        try {
            // Must return 0 rows (Fail-Closed)
            $this->assertEmpty(DB::table('fiscal_years')->get());
            $this->assertEmpty(DB::table('fee_categories')->get());
            $this->assertEmpty(DB::table('fee_invoices')->get());

            // Inserting with unset context must fail RLS WITH CHECK policy
            $injectionFailed = false;
            DB::statement('SAVEPOINT rls_failclosed_test');
            try {
                DB::table('fee_categories')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $this->tenantA->id,
                    'code' => 'ILLEGAL_CAT',
                    'name' => 'Illegal Category',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT rls_failclosed_test');
                $injectionFailed = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($injectionFailed, 'Insert with unset tenant context did not fail RLS.');
        } finally {
            try {
                DB::statement('RESET ROLE');
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    try { DB::rollBack(); } catch (\Throwable $t) {}
                }
                DB::statement('RESET ROLE');
            }
        }
    }

    public function test_cross_tenant_isolation_prevents_read_write_injection(): void
    {
        // Seed records for both tenants
        $catA = FeeCategory::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CAT_TENANT_A',
            'name' => 'Category Tenant A',
        ]);

        $catB = FeeCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CAT_TENANT_B',
            'name' => 'Category Tenant B',
        ]);

        $invA = FeeInvoice::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'student_id' => $this->studentA->id,
            'invoice_number' => 'INV-TENANT-A',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 8000.00,
        ]);

        $invB = FeeInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'student_id' => $this->studentB->id,
            'invoice_number' => 'INV-TENANT-B',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 9000.00,
        ]);

        // Switch to educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenantA->id);

        try {
            // 1. SELECT visibility: Only Tenant A records visible
            $visibleCats = DB::table('fee_categories')->pluck('id')->toArray();
            $visibleInvs = DB::table('fee_invoices')->pluck('id')->toArray();

            $this->assertContains($catA->id, $visibleCats);
            $this->assertNotContains($catB->id, $visibleCats);

            $this->assertContains($invA->id, $visibleInvs);
            $this->assertNotContains($invB->id, $visibleInvs);

            // 2. UPDATE isolation: Attempt to update Tenant B record affects 0 rows
            $affected = DB::table('fee_categories')->where('id', $catB->id)->update(['name' => 'Hacked Name']);
            $this->assertEquals(0, $affected);

            // 3. DELETE isolation: Attempt to delete Tenant B record affects 0 rows
            $deleted = DB::table('fee_invoices')->where('id', $invB->id)->delete();
            $this->assertEquals(0, $deleted);

            // 4. INSERT cross-tenant injection: Inserting with Tenant B id while authenticated as Tenant A fails RLS WITH CHECK
            $injectionFailed = false;
            DB::statement('SAVEPOINT rls_injection_test');
            try {
                DB::table('fee_categories')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $this->tenantB->id,
                    'code' => 'INJECTED_CAT',
                    'name' => 'Injected Cat',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT rls_injection_test');
                $injectionFailed = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($injectionFailed, "Cross-tenant insertion did not throw RLS violation exception.");
        } finally {
            try {
                DB::statement('RESET ROLE');
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    try { DB::rollBack(); } catch (\Throwable $t) {}
                }
                DB::statement('RESET ROLE');
            }
        }

        // Verify Tenant B records were unaffected
        $catB->refresh();
        $this->assertEquals('Category Tenant B', $catB->name);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invB->id]);
    }
}

