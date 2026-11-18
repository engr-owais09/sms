<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Services\StudentAdmissionService;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\SubscriptionBillingService;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Console\Command;

class SeedPilotDemoCommand extends Command
{
    protected $signature = 'educore:seed-pilot';
    protected $description = 'Seed an idempotent pilot school tenant for local/testing evaluation';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('SAFETY GUARD: educore:seed-pilot can only execute in local or testing environments.');
            return self::FAILURE;
        }

        (new SubscriptionPlanService())->seedDefaultPlans();
        (new \Database\Seeders\RoleAndPermissionSeeder())->run();
        $growthPlan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'pilot-lgs'],
            [
                'name' => 'Lahore Grammar Pilot Campus',
                'type' => 'school',
                'status' => 'active',
            ]
        );

        Domain::firstOrCreate(
            ['domain' => 'pilot.localhost'],
            ['tenant_id' => $tenant->id, 'is_primary' => true]
        );

        $branch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'LGS-PILOT'],
            [
                'name' => 'Gulberg Pilot Campus',
                'city' => 'Lahore',
                'is_main' => true,
            ]
        );

        app(TenantContext::class)->setTenant($tenant);
        app(TenantContext::class)->setBranch($branch);

        if (!TenantSubscription::where('tenant_id', $tenant->id)->exists()) {
            (new SubscriptionBillingService())->subscribeTenant($tenant, $growthPlan, false);
        }

        (new GeneralLedgerService())->seedDefaultChartOfAccounts($tenant->id);
        (new GradingScaleService())->seedDefaultFbiseScale($tenant->id);

        $session = AcademicSession::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => '2026-2027'],
            [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_current' => true,
            ]
        );

        $class9 = SchoolClass::firstOrCreate(
            ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Grade 9'],
            ['numeric_level' => 9, 'display_order' => 9]
        );

        $sectionA = SchoolSection::firstOrCreate(
            ['tenant_id' => $tenant->id, 'school_class_id' => $class9->id, 'name' => 'Section A'],
            ['capacity' => 40]
        );

        Subject::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MATH-9'],
            ['name' => 'Mathematics', 'type' => 'theory']
        );

        CampusRoom::firstOrCreate(
            ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'room_code' => 'R-101'],
            ['name' => 'Room 101', 'room_type' => 'classroom', 'capacity' => 40]
        );

        SchoolPeriod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'period_number' => 1],
            ['label' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'is_break' => false]
        );

        $pilotAdmin = User::firstOrCreate(
            ['email' => 'pilot.admin@educore.pk'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Pilot Principal',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        $ownerRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'institute_owner',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
        $tenantPermissions = [
            'institute.view_settings', 'institute.edit_settings', 'branch.manage', 'academics.manage',
            'students.view', 'students.create', 'students.edit', 'students.delete',
            'attendance.mark', 'attendance.view',
            'finance.view_vouchers', 'finance.collect_fee', 'finance.manage_structures', 'finance.cashier_closing',
            'exams.manage', 'exams.enter_marks', 'reports.view',
        ];
        $ownerRole->syncPermissions($tenantPermissions);
        setPermissionsTeamId($tenant->id);
        $pilotAdmin->assignRole($ownerRole);
        setPermissionsTeamId(null);

        if (!Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            (new StudentAdmissionService())->admitToSchool([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'academic_session_id' => $session->id,
                'school_section_id' => $sectionA->id,
                'first_name' => 'Bilal',
                'last_name' => 'Ahmed',
                'gender' => 'male',
                'date_of_birth' => '2012-03-23',
                'guardian_name' => 'Kamran Ahmed',
                'guardian_phone' => '+923001122334',
            ]);
        }

        $this->info("Pilot tenant [{$tenant->name}] seeded cleanly at pilot.localhost.");

        return self::SUCCESS;
    }
}
