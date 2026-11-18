<?php

namespace Tests\Feature\Exam;

use App\Core\Tenancy\PostgresTenantContext;
use App\Modules\Exam\Models\GradingScale;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamRlsSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected array $all8ExamTables = [
        'grading_scales',
        'grading_scale_bands',
        'exam_terms',
        'exam_papers',
        'exam_marks',
        'exam_results',
        'academy_assessments',
        'academy_assessment_scores',
    ];

    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS security audit requires PostgreSQL.');
        }

        $this->tenantA = Tenant::create([
            'name' => 'Exam RLS School A',
            'slug' => 'exam-rls-a',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Exam RLS School B',
            'slug' => 'exam-rls-b',
            'type' => 'school',
            'status' => 'active',
        ]);
    }

    public function test_all_8_exam_tables_have_rls_enabled_forced_and_active_policies(): void
    {
        $records = DB::select("
            SELECT c.relname as table_name, c.relrowsecurity as rls_enabled, c.relforcerowsecurity as rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND c.relname IN ('" . implode("', '", $this->all8ExamTables) . "')
        ");

        $catalog = [];
        foreach ($records as $r) {
            $catalog[$r->table_name] = [
                'enabled' => (bool) $r->rls_enabled,
                'forced' => (bool) $r->rls_forced,
            ];
        }

        $this->assertCount(8, $this->all8ExamTables);
        foreach ($this->all8ExamTables as $table) {
            $this->assertArrayHasKey($table, $catalog, "Missing table {$table}");
            $this->assertTrue($catalog[$table]['enabled'], "RLS not enabled on {$table}");
            $this->assertTrue($catalog[$table]['forced'], "FORCE RLS not enabled on {$table}");
        }

        $policies = DB::select("
            SELECT tablename
            FROM pg_policies
            WHERE schemaname = 'public'
              AND tablename IN ('" . implode("', '", $this->all8ExamTables) . "')
        ");
        $found = array_unique(array_map(fn ($p) => $p->tablename, $policies));
        foreach ($this->all8ExamTables as $table) {
            $this->assertContains($table, $found, "Missing RLS policy on {$table}");
        }
    }

    public function test_fail_closed_and_cross_tenant_isolation_on_exam_tables(): void
    {
        $scaleA = GradingScale::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Scale A',
            'scale_type' => 'percentage',
        ]);

        $scaleB = GradingScale::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Scale B',
            'scale_type' => 'percentage',
        ]);

        DB::statement('SET ROLE educore_app');
        DB::statement('RESET app.current_tenant_id');

        try {
            $this->assertEmpty(DB::table('grading_scales')->get());

            PostgresTenantContext::set($this->tenantA->id);

            $visible = DB::table('grading_scales')->pluck('id')->toArray();
            $this->assertContains($scaleA->id, $visible);
            $this->assertNotContains($scaleB->id, $visible);

            $this->assertEquals(0, DB::table('grading_scales')->where('id', $scaleB->id)->update(['name' => 'Hacked']));

            $blocked = false;
            DB::statement('SAVEPOINT exam_rls_inject');
            try {
                DB::table('grading_scales')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $this->tenantB->id,
                    'name' => 'Injected Scale',
                    'scale_type' => 'percentage',
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT exam_rls_inject');
                $blocked = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($blocked);
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
}
