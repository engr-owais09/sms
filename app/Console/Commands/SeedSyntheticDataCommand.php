<?php

namespace App\Console\Commands;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Student;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedSyntheticDataCommand extends Command
{
    protected $signature = 'educore:seed-synthetic {--tenants=2 : Number of synthetic tenants} {--students=50 : Students per tenant}';

    protected $description = 'Generate deterministic synthetic multi-tenant data for EXPLAIN and load testing (blocked in production)';

    public function handle(TenantProvisioningService $provisioning): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run synthetic data generator in production.');
            return self::FAILURE;
        }

        (new RoleAndPermissionSeeder())->run();

        $tenantCount = max(1, (int) $this->option('tenants'));
        $studentsPerTenant = max(1, (int) $this->option('students'));

        for ($t = 1; $t <= $tenantCount; $t++) {
            $slug = 'synth-campus-'.$t.'-'.strtolower(Str::random(4));
            $prov = $provisioning->provision([
                'name' => "Synthetic Campus {$t}",
                'slug' => $slug,
                'type' => 'school',
                'owner_name' => "Synth Owner {$t}",
                'owner_email' => "owner.{$slug}@synthetic.test",
                'owner_password' => 'SynthPass#2026',
            ]);

            $tenant = $prov['tenant'];
            $branch = $prov['branch'];

            app(TenantContext::class)->setTenant($tenant, $branch);
            PostgresTenantContext::set($tenant->id);

            $session = AcademicSession::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => '2026-2027'],
                ['start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]
            );

            $class = SchoolClass::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Grade 9'],
                ['numeric_level' => 9, 'display_order' => 9]
            );

            $section = SchoolSection::firstOrCreate(
                ['tenant_id' => $tenant->id, 'school_class_id' => $class->id, 'name' => 'Section A'],
                ['max_capacity' => $studentsPerTenant + 50]
            );

            for ($s = 1; $s <= $studentsPerTenant; $s++) {
                $student = Student::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'admission_number' => sprintf('SYN-%d-%05d', $t, $s),
                    'first_name' => 'Student',
                    'last_name' => (string) $s,
                    'date_of_birth' => '2012-05-15',
                    'admission_date' => '2026-04-01',
                    'gender' => $s % 2 === 0 ? 'female' : 'male',
                    'status' => 'active',
                ]);

                SchoolStudentEnrollment::create([
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'school_section_id' => $section->id,
                    'roll_number' => (string) $s,
                    'enrolled_at' => '2026-04-01',
                    'status' => 'active',
                ]);
            }

            PostgresTenantContext::reset();
            app(TenantContext::class)->clear();
        }

        $this->info("Seeded {$tenantCount} synthetic tenant(s) with {$studentsPerTenant} student(s) each.");

        return self::SUCCESS;
    }
}
