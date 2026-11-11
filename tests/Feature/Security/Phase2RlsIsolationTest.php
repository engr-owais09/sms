<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2RlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantAlpha;
    protected Tenant $tenantBeta;
    protected Branch $branchAlpha;
    protected Branch $branchBeta;
    protected Student $studentAlpha;
    protected Student $studentBeta;
    protected Guardian $guardianAlpha;
    protected Guardian $guardianBeta;
    protected SchoolClass $classAlpha;
    protected SchoolClass $classBeta;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant Alpha
        $this->tenantAlpha = Tenant::create([
            'name' => 'Roots Millennium',
            'slug' => 'roots-alpha',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchAlpha = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Roots Capital',
            'code' => 'RC-01',
            'city' => 'Islamabad',
            'is_main' => true,
        ]);

        // Tenant Beta
        $this->tenantBeta = Tenant::create([
            'name' => 'Froebels International',
            'slug' => 'froebels-beta',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branchBeta = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantBeta->id,
            'name' => 'Froebels F-7',
            'code' => 'FF-01',
            'city' => 'Islamabad',
            'is_main' => true,
        ]);

        // Create records without scopes for clean seeding
        $this->studentAlpha = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlpha->id,
            'branch_id' => $this->branchAlpha->id,
            'admission_number' => 'ROOTS-2026-0001',
            'first_name' => 'Zain',
            'last_name' => 'Abbas',
            'gender' => 'male',
            'date_of_birth' => '2012-03-10',
            'admission_date' => now()->toDateString(),
        ]);

        $this->studentBeta = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantBeta->id,
            'branch_id' => $this->branchBeta->id,
            'admission_number' => 'FROEB-2026-0001',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'gender' => 'female',
            'date_of_birth' => '2012-07-15',
            'admission_date' => now()->toDateString(),
        ]);

        $this->guardianAlpha = Guardian::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlpha->id,
            'first_name' => 'Abbas',
            'last_name' => 'Raza',
            'phone' => '+923001112233',
        ]);

        $this->guardianBeta = Guardian::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantBeta->id,
            'first_name' => 'Ali',
            'last_name' => 'Nawaz',
            'phone' => '+923009998877',
        ]);

        $this->classAlpha = SchoolClass::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlpha->id,
            'branch_id' => $this->branchAlpha->id,
            'name' => 'Grade 8 Alpha',
            'numeric_level' => 8,
            'display_order' => 8,
        ]);

        $this->classBeta = SchoolClass::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantBeta->id,
            'branch_id' => $this->branchBeta->id,
            'name' => 'Grade 8 Beta',
            'numeric_level' => 8,
            'display_order' => 8,
        ]);
    }

    public function test_eloquent_global_scope_isolates_phase2_models(): void
    {
        // Switch to Tenant Alpha
        app(TenantContext::class)->setTenant($this->tenantAlpha);

        $students = Student::all();
        $this->assertCount(1, $students);
        $this->assertTrue($students->contains($this->studentAlpha));
        $this->assertFalse($students->contains($this->studentBeta));

        $guardians = Guardian::all();
        $this->assertCount(1, $guardians);
        $this->assertTrue($guardians->contains($this->guardianAlpha));
        $this->assertFalse($guardians->contains($this->guardianBeta));

        $classes = SchoolClass::all();
        $this->assertCount(1, $classes);
        $this->assertTrue($classes->contains($this->classAlpha));
        $this->assertFalse($classes->contains($this->classBeta));

        // Switch to Tenant Beta
        app(TenantContext::class)->setTenant($this->tenantBeta);

        $studentsBeta = Student::all();
        $this->assertCount(1, $studentsBeta);
        $this->assertTrue($studentsBeta->contains($this->studentBeta));
        $this->assertFalse($studentsBeta->contains($this->studentAlpha));
    }

    public function test_postgres_native_rls_filters_direct_sql_under_unprivileged_role(): void
    {
        DB::transaction(function () {
            // Assume runtime unprivileged role with NOBYPASSRLS
            DB::statement("SET ROLE educore_app");
            PostgresTenantContext::set($this->tenantAlpha->id);

            // Direct SQL query on students table
            $rows = DB::select("SELECT * FROM students");
            $this->assertCount(1, $rows);
            $this->assertEquals($this->studentAlpha->id, $rows[0]->id);

            // Attempting to filter by Tenant Beta ID explicitly returns 0 rows because RLS filters it out
            $crossRows = DB::select("SELECT * FROM students WHERE tenant_id = ?", [$this->tenantBeta->id]);
            $this->assertCount(0, $crossRows, 'RLS must hide Tenant Beta rows from Tenant Alpha context');

            // Direct SQL query on classes
            $classRows = DB::select("SELECT * FROM school_classes");
            $this->assertCount(1, $classRows);
            $this->assertEquals($this->classAlpha->id, $classRows[0]->id);

            DB::statement("SET ROLE NONE");
        });
    }

    public function test_postgres_native_rls_prevents_cross_tenant_insert_injection(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function () {
            DB::statement("SET ROLE educore_app");
            // Set session context to Tenant Alpha
            PostgresTenantContext::set($this->tenantAlpha->id);

            try {
                // Malicious cross-tenant injection: Tenant Alpha session attempting to insert a row belonging to Tenant Beta
                DB::table('students')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $this->tenantBeta->id, // INJECTING TENANT BETA
                    'branch_id' => $this->branchBeta->id,
                    'admission_number' => 'INJECT-001',
                    'first_name' => 'Injected',
                    'last_name' => 'Attacker',
                    'gender' => 'male',
                    'date_of_birth' => '2012-01-01',
                    'admission_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } finally {
                DB::statement("SET ROLE NONE");
            }
        });
    }
}

