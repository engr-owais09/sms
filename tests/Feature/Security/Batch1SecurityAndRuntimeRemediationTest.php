<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\Contracts\TenantAwareJob;
use App\Core\Tenancy\Middleware\EnforceTenantQueueMiddleware;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Jobs\DispatchAbsenteeNotificationsJob;
use App\Modules\Attendance\Livewire\LeaveApprovalConsole;
use App\Modules\Attendance\Livewire\RollCallRegister;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use App\Modules\Exam\Livewire\MarksEntryConsole;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Finance\Jobs\GenerateSchoolMonthlyVouchersJob;
use App\Modules\Finance\Livewire\CashierShiftManager;
use App\Modules\Finance\Livewire\CashierTerminal;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Student;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Batch1SecurityAndRuntimeRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $ownerA;
    protected User $ownerB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleAndPermissionSeeder())->run();
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@educore.test',
            'is_super_admin' => true,
            'tenant_id' => null,
            'branch_id' => null,
            'status' => 'active',
        ]);
        setPermissionsTeamId(null);
        $this->superAdmin->assignRole('super_admin');

        $provA = (new TenantProvisioningService())->provision([
            'name' => 'Alpha Grammar School',
            'slug' => 'alpha-school',
            'type' => 'school',
            'owner_name' => 'Alpha Owner',
            'owner_email' => 'owner@alpha.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Alpha Main',
            'city' => 'Lahore',
        ]);
        $this->tenantA = $provA['tenant'];
        $this->branchA = $provA['branch'];
        $this->ownerA = $provA['owner'];

        Domain::firstOrCreate(
            ['domain' => 'alpha.localhost'],
            ['tenant_id' => $this->tenantA->id, 'is_primary' => true, 'is_verified' => true]
        );

        $provB = (new TenantProvisioningService())->provision([
            'name' => 'Beta Grammar School',
            'slug' => 'beta-school',
            'type' => 'school',
            'owner_name' => 'Beta Owner',
            'owner_email' => 'owner@beta.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Beta Main',
            'city' => 'Karachi',
        ]);
        $this->tenantB = $provB['tenant'];
        $this->branchB = $provB['branch'];
        $this->ownerB = $provB['owner'];

        Domain::firstOrCreate(
            ['domain' => 'beta.localhost'],
            ['tenant_id' => $this->tenantB->id, 'is_primary' => true, 'is_verified' => true]
        );

        PostgresTenantContext::reset();
        app(TenantContext::class)->clear();
    }

    public function test_def_s01_http_requests_set_and_reset_postgres_tenant_context_per_tenant(): void
    {
        Route::middleware('web')->get('/_test/pg-tenant-context', function () {
            return response()->json([
                'app_tenant_id' => app(TenantContext::class)->getTenantId(),
                'pg_tenant_id' => PostgresTenantContext::get(),
                'raw_pg_setting' => DB::selectOne("SELECT current_setting('app.current_tenant_id', true) AS tid")?->tid,
            ]);
        });

        // 1. Request to Tenant A sets PostgreSQL app.current_tenant_id to Tenant A
        $resA = $this->actingAs($this->ownerA)
            ->get('http://alpha.localhost/_test/pg-tenant-context')
            ->assertOk()
            ->json();

        $this->assertEquals($this->tenantA->id, $resA['app_tenant_id']);
        $this->assertEquals($this->tenantA->id, $resA['pg_tenant_id']);
        $this->assertEquals($this->tenantA->id, $resA['raw_pg_setting']);

        // Middleware terminate() must reset PostgreSQL context after response
        $this->assertNull(PostgresTenantContext::get());

        // 2. Request to Tenant B sets PostgreSQL app.current_tenant_id to Tenant B
        $resB = $this->actingAs($this->ownerB)
            ->get('http://beta.localhost/_test/pg-tenant-context')
            ->assertOk()
            ->json();

        $this->assertEquals($this->tenantB->id, $resB['app_tenant_id']);
        $this->assertEquals($this->tenantB->id, $resB['pg_tenant_id']);
        $this->assertEquals($this->tenantB->id, $resB['raw_pg_setting']);
        $this->assertNull(PostgresTenantContext::get());

        // 3. Request to Central host keeps PostgreSQL tenant context empty
        $resCentral = $this->actingAs($this->superAdmin)
            ->get('http://localhost/_test/pg-tenant-context')
            ->assertOk()
            ->json();

        $this->assertNull($resCentral['app_tenant_id']);
        $this->assertNull($resCentral['pg_tenant_id']);
        $this->assertEmpty($resCentral['raw_pg_setting']);
    }

    public function test_def_s01_non_superuser_educore_app_role_enforces_rls_and_has_least_privilege_grants(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required.');
        }

        // 1. Verify role attributes: educore_migrator is superuser, educore_app is non-superuser & nobypassrls
        $roles = collect(DB::select("
            SELECT rolname, rolsuper, rolbypassrls, rolcanlogin
            FROM pg_roles
            WHERE rolname IN ('educore_migrator', 'educore_app')
        "))->keyBy('rolname');

        $this->assertTrue((bool) $roles['educore_migrator']->rolsuper);
        $this->assertFalse((bool) $roles['educore_app']->rolsuper, 'educore_app must NOT be a superuser');
        $this->assertFalse((bool) $roles['educore_app']->rolbypassrls, 'educore_app must NOT have BYPASSRLS');
        $this->assertTrue((bool) $roles['educore_app']->rolcanlogin);

        // 2. Verify educore_app has SELECT/INSERT privileges on central and tenant tables
        $unprivilegedTables = DB::select("
            SELECT c.relname AS table_name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND c.relname <> 'migrations'
              AND (
                  NOT has_table_privilege('educore_app', c.oid, 'SELECT')
                  OR NOT has_table_privilege('educore_app', c.oid, 'INSERT')
              )
        ");
        $this->assertEmpty($unprivilegedTables, 'educore_app is missing table privileges.');

        // 3. Seed one student per tenant
        $studentA = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'admission_number' => 'ALPHA-001',
            'first_name' => 'Ali',
            'last_name' => 'Alpha',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $studentB = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'admission_number' => 'BETA-001',
            'first_name' => 'Bilal',
            'last_name' => 'Beta',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        // 4. Execute under educore_app role and verify custom domain resolver + strict RLS isolation
        DB::statement('SET ROLE educore_app');
        try {
            PostgresTenantContext::reset();

            // Custom domain lookup function works even before tenant context is set
            $resolvedId = DB::selectOne('SELECT public.resolve_tenant_id_by_domain(?) AS tenant_id', ['alpha.localhost'])?->tenant_id;
            $this->assertEquals($this->tenantA->id, $resolvedId);

            // Direct table query on domains with no tenant context returns 0 rows under educore_app
            $this->assertCount(0, DB::select('SELECT * FROM domains'));

            // Activate Tenant A context via HTTP-style session config (isLocal: false)
            PostgresTenantContext::set($this->tenantA->id, isLocal: false);

            $visibleStudents = DB::select('SELECT id, tenant_id FROM students');
            $this->assertCount(1, $visibleStudents);
            $this->assertEquals($studentA->id, $visibleStudents[0]->id);

            // Cross-tenant INSERT must be rejected by PostgreSQL RLS WITH CHECK policy
            DB::statement('SAVEPOINT rls_cross_tenant_write');
            $rlsBlocked = false;
            try {
                DB::table('students')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $this->tenantB->id,
                    'branch_id' => $this->branchB->id,
                    'admission_number' => 'FORGED-999',
                    'first_name' => 'Forged',
                    'last_name' => 'Student',
                    'date_of_birth' => '2012-01-01',
                    'admission_date' => '2026-04-01',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT rls_cross_tenant_write');
                $rlsBlocked = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($rlsBlocked, 'educore_app must be blocked from inserting rows for another tenant.');
        } finally {
            PostgresTenantContext::reset();
            DB::statement('RESET ROLE');
        }
    }

    public function test_def_s01_dedicated_pgsql_app_connection_runs_as_educore_app_and_blocks_privilege_escalation(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required.');
        }

        config(['database.connections.pgsql_app.password' => env('DB_APP_PASSWORD', 'app_secret_2026')]);
        DB::purge('pgsql_app');
        $appConn = DB::connection('pgsql_app');

        $identity = $appConn->selectOne('SELECT current_user, session_user');
        $this->assertEquals('educore_app', $identity->current_user);
        $this->assertEquals('educore_app', $identity->session_user);

        // Attempt privilege escalation to educore_migrator must fail with 42501 permission denied
        $escalationBlocked = false;
        try {
            $appConn->statement('SET ROLE educore_migrator');
        } catch (QueryException $e) {
            $escalationBlocked = true;
            $this->assertStringContainsString('permission denied to set role', $e->getMessage());
        }
        $this->assertTrue($escalationBlocked, 'educore_app must not be able to SET ROLE educore_migrator.');
    }

    public function test_def_s04_queue_jobs_implement_tenant_aware_contract_and_attach_middleware(): void
    {
        $voucherJob = new GenerateSchoolMonthlyVouchersJob(
            $this->tenantA->id,
            $this->branchA->id,
            (string) Str::uuid(),
            '2026-11',
            '2026-11-01',
            '2026-11-10'
        );

        $absenteeJob = new DispatchAbsenteeNotificationsJob(
            $this->tenantA->id,
            'school_attendance',
            (string) Str::uuid(),
            'sms'
        );

        $this->assertInstanceOf(TenantAwareJob::class, $voucherJob);
        $this->assertInstanceOf(TenantAwareJob::class, $absenteeJob);
        $this->assertEquals($this->tenantA->id, $voucherJob->getTenantId());
        $this->assertEquals($this->tenantA->id, $absenteeJob->getTenantId());

        $voucherMiddleware = $voucherJob->middleware();
        $absenteeMiddleware = $absenteeJob->middleware();

        $this->assertNotEmpty($voucherMiddleware);
        $this->assertNotEmpty($absenteeMiddleware);
        $this->assertInstanceOf(EnforceTenantQueueMiddleware::class, $voucherMiddleware[0]);
        $this->assertInstanceOf(EnforceTenantQueueMiddleware::class, $absenteeMiddleware[0]);
    }

    public function test_def_s05_unauthenticated_livewire_and_service_calls_do_not_fallback_to_super_admin(): void
    {
        app(TenantContext::class)->setTenant($this->tenantA);
        app(TenantContext::class)->setBranch($this->branchA);
        PostgresTenantContext::set($this->tenantA->id);

        // Seed minimal fixtures
        (new GeneralLedgerService())->seedDefaultChartOfAccounts($this->tenantA->id);

        $student = Student::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'admission_number' => 'ALPHA-S05',
            'first_name' => 'Zaid',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-02-02',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        // Ensure no user is authenticated
        auth()->logout();
        $this->assertNull(auth()->user());

        // 1. CashierShiftManager::openShift must throw AuthenticationException and not open shift for User::first()
        $shiftComponent = new CashierShiftManager();
        $shiftComponent->openingFloat = 5000.00;
        try {
            $shiftComponent->openShift();
            $this->fail('Expected AuthenticationException on unauthenticated openShift().');
        } catch (AuthenticationException) {
            $this->assertEquals(0, CashierSession::count());
        }

        // 2. CashierTerminal::processPayment must throw AuthenticationException
        $terminalComponent = new CashierTerminal();
        $terminalComponent->selectedStudent = $student;
        $terminalComponent->amountTendered = 1000.00;
        try {
            $terminalComponent->processPayment();
            $this->fail('Expected AuthenticationException on unauthenticated CashierTerminal::processPayment().');
        } catch (AuthenticationException) {
            $this->assertTrue(true);
        }

        // 3. LeaveApprovalConsole::approve and reject must throw AuthenticationException
        $leave = StudentLeaveRequest::create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'student_id' => $student->id,
            'leave_type' => 'sick',
            'start_date' => '2026-11-18',
            'end_date' => '2026-11-18',
            'reason' => 'Fever',
            'status' => 'pending',
        ]);
        $leaveComponent = new LeaveApprovalConsole();
        try {
            $leaveComponent->approve($leave->id);
            $this->fail('Expected AuthenticationException on unauthenticated LeaveApprovalConsole::approve().');
        } catch (AuthenticationException) {
            $this->assertEquals('pending', $leave->fresh()->status);
        }

        // 4. RollCallRegister::saveAttendance must throw AuthenticationException
        $rollCallComponent = new RollCallRegister();
        $rollCallComponent->activeSessionId = (string) Str::uuid();
        try {
            $rollCallComponent->saveAttendance(false);
            $this->fail('Expected AuthenticationException on unauthenticated RollCallRegister::saveAttendance().');
        } catch (AuthenticationException) {
            $this->assertTrue(true);
        }

        // 5. MarksEntryConsole::saveMarks must throw AuthenticationException
        $marksComponent = new MarksEntryConsole();
        $marksComponent->selectedPaperId = (string) Str::uuid();
        $marksComponent->selectedSectionId = (string) Str::uuid();
        $marksComponent->markRows = [['student_id' => $student->id, 'marks' => 50, 'is_absent' => false, 'remarks' => '']];
        try {
            $marksComponent->saveMarks();
            $this->fail('Expected AuthenticationException on unauthenticated MarksEntryConsole::saveMarks().');
        } catch (AuthenticationException) {
            $this->assertTrue(true);
        }

        // 6. PaymentProcessingService::processPayment without received_by_user_id or auth must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        (new PaymentProcessingService())->processPayment($student, 1000.00, ['payment_method' => 'cash']);
    }
}
