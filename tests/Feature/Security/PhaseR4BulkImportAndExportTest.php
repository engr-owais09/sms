<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Attendance\Services\AttendanceBulkImportService;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\OpeningBalanceImportService;
use App\Modules\Institute\Services\StaffBulkImportService;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Services\BulkPromotionService;
use App\Modules\Student\Services\StudentBulkImportService;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseR4BulkImportAndExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_bulk_import_supports_dry_run_sibling_linking_idempotency_and_quota(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Import School',
            'slug' => 'import-school',
            'type' => 'school',
            'owner_name' => 'Import Owner',
            'owner_email' => 'owner@import.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        $branch = $prov['branch'];

        $sub = TenantSubscription::with('plan')->where('tenant_id', $tenant->id)->firstOrFail();
        $sub->plan->update(['max_students' => 3]);

        app(TenantContext::class)->setTenant($tenant, $branch);
        PostgresTenantContext::set($tenant->id);

        $csv = "\xEF\xBB\xBFadmission_number,first_name,last_name,date_of_birth,gender,guardian_name,guardian_phone,guardian_cnic\n"
            ."IMP-001,Zara,Khan,2012-01-10,female,Tariq Khan,03001112222,35202-1111111-1\n"
            ."IMP-002,Hamza,Khan,2014-03-15,male,Tariq Khan,03001112222,35202-1111111-1\n"
            ."BAD-ROW,,Missing,invalid-date,unknown,,,\n";

        $service = app(StudentBulkImportService::class);

        // Dry run validates without inserting
        $dry = $service->process($csv, $tenant, $branch, dryRun: true, actorUserId: $prov['owner']->id);
        $this->assertSame(3, $dry['total']);
        $this->assertSame(2, $dry['valid']);
        $this->assertSame(0, $dry['imported']);
        $this->assertCount(1, $dry['errors']);
        $this->assertSame(0, Student::where('tenant_id', $tenant->id)->count());

        // Commit inserts 2 students and deduplicates sibling guardian by CNIC
        $commit = $service->process($csv, $tenant, $branch, dryRun: false, actorUserId: $prov['owner']->id);
        $this->assertSame(2, $commit['imported']);
        $this->assertSame(2, Student::where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, Guardian::where('tenant_id', $tenant->id)->count());

        // Re-running same CSV skips existing admission numbers idempotently
        $repeat = $service->process($csv, $tenant, $branch, dryRun: false, actorUserId: $prov['owner']->id);
        $this->assertSame(0, $repeat['imported']);
        $this->assertSame(2, $repeat['skipped']);

        // Exceeding max_students (3) throws QuotaExceededException
        $overQuotaCsv = "admission_number,first_name,last_name,date_of_birth,gender\n"
            ."IMP-003,A,B,2013-01-01,male\n"
            ."IMP-004,C,D,2013-01-01,female\n";
        $this->expectException(QuotaExceededException::class);
        $service->process($overQuotaCsv, $tenant, $branch, dryRun: false, actorUserId: $prov['owner']->id);
    }

    public function test_staff_bulk_import_prevents_owner_privilege_escalation(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Staff Import School',
            'slug' => 'staff-imp',
            'type' => 'school',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@staffimp.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        $branch = $prov['branch'];

        $principal = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        setPermissionsTeamId($tenant->id);
        $pRole = Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $pRole->givePermissionTo(Permission::firstOrCreate(['name' => 'institute.edit_settings', 'guard_name' => 'web']));
        $principal->assignRole($pRole);

        $csv = "name,email,phone,role\nEvil User,evil@staffimp.test,03000000000,institute_owner\n";
        $this->expectException(AuthorizationException::class);
        app(StaffBulkImportService::class)->process($csv, $tenant, $branch, $principal, dryRun: false);
    }

    public function test_streaming_student_export_and_templates_enforce_tenant_isolation(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $provA = app(TenantProvisioningService::class)->provision([
            'name' => 'Export Stream A',
            'slug' => 'stream-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@stream.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $provB = app(TenantProvisioningService::class)->provision([
            'name' => 'Export Stream B',
            'slug' => 'stream-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@stream.test',
            'owner_password' => 'StrongPass123!',
        ]);

        app(TenantContext::class)->setTenant($provA['tenant'], $provA['branch']);
        PostgresTenantContext::set($provA['tenant']->id);
        Student::create([
            'tenant_id' => $provA['tenant']->id,
            'branch_id' => $provA['branch']->id,
            'admission_number' => 'STR-A-1',
            'first_name' => 'Sana',
            'last_name' => 'Tariq',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'female',
            'status' => 'active',
        ]);

        app(TenantContext::class)->setTenant($provB['tenant'], $provB['branch']);
        PostgresTenantContext::set($provB['tenant']->id);
        Student::create([
            'tenant_id' => $provB['tenant']->id,
            'branch_id' => $provB['branch']->id,
            'admission_number' => 'STR-B-HIDDEN',
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->actingAs($provA['owner'])
            ->get('http://stream-a.educore.test/students/import/template?lang=ur')
            ->assertOk();

        $export = $this->actingAs($provA['owner'])
            ->get('http://stream-a.educore.test/students/export');
        $export->assertOk();
        $csvOutput = $export->streamedContent();
        $this->assertStringContainsString('STR-A-1', $csvOutput);
        $this->assertStringNotContainsString('STR-B-HIDDEN', $csvOutput);

        // HTTP upload endpoint works
        $file = UploadedFile::fake()->createWithContent(
            'students.csv',
            "admission_number,first_name,last_name,date_of_birth,gender\nSTR-A-2,Omar,Ali,2012-06-06,male\n"
        );
        $this->actingAs($provA['owner'])
            ->post('http://stream-a.educore.test/students/import', ['csv_file' => $file, 'dry_run' => 0])
            ->assertRedirect();
        $this->assertDatabaseHas('students', ['tenant_id' => $provA['tenant']->id, 'admission_number' => 'STR-A-2']);
    }

    public function test_opening_balance_attendance_backfill_and_bulk_promotion_respect_locks_gl_and_isolation(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Bulk Ops School',
            'slug' => 'bulk-ops',
            'type' => 'school',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@bulkops.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        $branch = $prov['branch'];
        $owner = $prov['owner'];

        app(TenantContext::class)->setTenant($tenant, $branch);
        PostgresTenantContext::set($tenant->id);
        app(GeneralLedgerService::class)->seedDefaultChartOfAccounts($tenant->id);

        $student = Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => 'BLK-001',
            'first_name' => 'Nadia',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'female',
            'status' => 'active',
        ]);

        // R4.4 Opening Balance Import -> Balanced GL
        $balCsv = "admission_number,amount_pkr,due_date,description\nBLK-001,7500,2026-06-10,Prior year arrears\n";
        $balRes = app(OpeningBalanceImportService::class)->process($balCsv, $tenant, $branch, $owner, dryRun: false);
        $this->assertSame(1, $balRes['imported']);
        $inv = FeeInvoice::where('tenant_id', $tenant->id)->where('invoice_number', 'INV-OPEN-BLK-001')->firstOrFail();
        $entry = JournalEntry::with('lines')->findOrFail($inv->journal_entry_id);
        $this->assertEquals(7500.0, (float) $entry->lines->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(7500.0, (float) $entry->lines->where('entry_type', 'credit')->sum('amount'));

        // R4.5 Attendance Backfill respects locked sessions
        $session = AcademicSession::create(['tenant_id' => $tenant->id, 'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $class = SchoolClass::create(['tenant_id' => $tenant->id, 'name' => 'Grade 8', 'numeric_level' => 8, 'display_order' => 8]);
        $section = SchoolSection::create(['tenant_id' => $tenant->id, 'school_class_id' => $class->id, 'name' => 'Section A', 'max_capacity' => 40]);

        SchoolAttendanceSession::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $section->id,
            'attendance_date' => '2026-05-01',
            'marked_by_user_id' => $owner->id,
            'status' => 'locked',
        ]);

        $attCsv = "admission_number,attendance_date,status,remarks\nBLK-001,2026-05-01,present,Locked day\nBLK-001,2026-05-02,absent,Sick\n";
        $attRes = app(AttendanceBulkImportService::class)->process($attCsv, $tenant, $branch, $session, $section, $owner, dryRun: false);
        $this->assertSame(1, $attRes['imported']);
        $this->assertCount(1, $attRes['errors']);
        $this->assertSame(1, SchoolAttendanceRecord::where('tenant_id', $tenant->id)->count());

        // R4.6 Bulk Promotion
        $nextClass = SchoolClass::create(['tenant_id' => $tenant->id, 'name' => 'Grade 9', 'numeric_level' => 9, 'display_order' => 9]);
        $nextSection = SchoolSection::create(['tenant_id' => $tenant->id, 'school_class_id' => $nextClass->id, 'name' => 'Section A', 'max_capacity' => 40]);
        $promoted = app(BulkPromotionService::class)->promote($tenant, [$student->id], $session, $nextSection, $owner->id);
        $this->assertSame(1, $promoted);
    }
}
