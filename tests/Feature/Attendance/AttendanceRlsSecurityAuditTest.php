<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceRlsSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected array $all10AttendanceTables = [
        'campus_rooms',
        'academic_holidays',
        'school_periods',
        'school_timetable_entries',
        'school_attendance_sessions',
        'school_attendance_records',
        'academy_attendance_sessions',
        'academy_attendance_records',
        'student_leave_requests',
        'attendance_notifications',
    ];

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Branch $branchA;
    protected Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS security audit requires PostgreSQL.');
        }

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A School',
            'slug' => 'att-rls-a',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Branch A',
            'code' => 'BR-A',
            'city' => 'Lahore',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant B School',
            'slug' => 'att-rls-b',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Branch B',
            'code' => 'BR-B',
            'city' => 'Karachi',
        ]);
    }

    public function test_all_10_attendance_tables_have_rls_enabled_and_forced(): void
    {
        $records = DB::select("
            SELECT c.relname as table_name, c.relrowsecurity as rls_enabled, c.relforcerowsecurity as rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND c.relname IN ('" . implode("', '", $this->all10AttendanceTables) . "')
        ");

        $catalog = [];
        foreach ($records as $r) {
            $catalog[$r->table_name] = [
                'enabled' => (bool) $r->rls_enabled,
                'forced' => (bool) $r->rls_forced,
            ];
        }

        $this->assertCount(10, $this->all10AttendanceTables);
        foreach ($this->all10AttendanceTables as $table) {
            $this->assertArrayHasKey($table, $catalog, "Missing table {$table}");
            $this->assertTrue($catalog[$table]['enabled'], "RLS not enabled on {$table}");
            $this->assertTrue($catalog[$table]['forced'], "FORCE RLS not enabled on {$table}");
        }
    }

    public function test_all_10_attendance_tables_have_active_policies_for_educore_app(): void
    {
        $policies = DB::select("
            SELECT tablename
            FROM pg_policies
            WHERE schemaname = 'public'
              AND tablename IN ('" . implode("', '", $this->all10AttendanceTables) . "')
        ");

        $foundTables = array_unique(array_map(fn ($p) => $p->tablename, $policies));
        foreach ($this->all10AttendanceTables as $table) {
            $this->assertContains($table, $foundTables, "Missing RLS policy on {$table}");
        }
    }

    public function test_fail_closed_and_cross_tenant_isolation_on_attendance_tables(): void
    {
        $roomA = CampusRoom::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'room_code' => 'A-101',
            'name' => 'Room A',
            'capacity' => 30,
        ]);

        $roomB = CampusRoom::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'room_code' => 'B-101',
            'name' => 'Room B',
            'capacity' => 30,
        ]);

        DB::statement('SET ROLE educore_app');
        DB::statement('RESET app.current_tenant_id');

        try {
            $this->assertEmpty(DB::table('campus_rooms')->get());

            // Now set Tenant A context
            PostgresTenantContext::set($this->tenantA->id);

            $visibleRooms = DB::table('campus_rooms')->pluck('id')->toArray();
            $this->assertContains($roomA->id, $visibleRooms);
            $this->assertNotContains($roomB->id, $visibleRooms);

            // Cross-tenant update affects 0 rows
            $this->assertEquals(0, DB::table('campus_rooms')->where('id', $roomB->id)->update(['name' => 'Hacked']));

            // Cross-tenant insert blocked by RLS WITH CHECK
            $blocked = false;
            DB::statement('SAVEPOINT att_rls_inject');
            try {
                DB::table('campus_rooms')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $this->tenantB->id,
                    'branch_id' => $this->branchB->id,
                    'room_code' => 'HACK-1',
                    'name' => 'Hacked Room',
                    'room_type' => 'classroom',
                    'capacity' => 20,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT att_rls_inject');
                $blocked = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($blocked, 'Cross-tenant insert into campus_rooms was not blocked by RLS.');
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
