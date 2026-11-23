<?php

namespace App\Modules\Institute\Services;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FiscalYear;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\QuotaEnforcementService;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantProvisioningService
{
    /**
     * Provision a brand new institute workspace with isolated database scoping.
     * Supports idempotent retries within a single atomic transaction.
     *
     * @param array<string, mixed> $data
     * @return array{tenant: Tenant, owner: User, branch: Branch, session?: ?AcademicSession, invited_staff?: array<string, User>}
     */
    public function provision(array $data): array
    {
        if (empty($data['owner_password']) || strlen((string) $data['owner_password']) < 8) {
            throw new InvalidArgumentException('A valid owner password (minimum 8 characters) is required for tenant provisioning.');
        }

        $rawType = $data['type'] ?? 'school';
        if (!in_array($rawType, ['school', 'academy', 'hybrid', 'both'], true)) {
            throw new InvalidArgumentException("Invalid institute type [{$rawType}]. Allowed: school, academy, hybrid, both.");
        }
        $normalizedType = $rawType === 'both' ? 'hybrid' : $rawType;

        return DB::transaction(function () use ($data, $normalizedType) {
            // 1. Create or update Tenant (idempotent by slug)
            $slug = Str::slug($data['slug'] ?? Str::slug($data['name']));

            $settings = array_filter([
                'brand_color' => $data['brand_color'] ?? '#1e40af',
                'contact_phone' => $data['owner_phone'] ?? null,
                'registration_no' => $data['registration_no'] ?? null,
                'working_days' => $data['working_days'] ?? null,
                'fee_due_day' => $data['fee_due_day'] ?? null,
                'late_fee_type' => $data['late_fee_type'] ?? null,
                'late_fee_amount' => $data['late_fee_amount'] ?? null,
                'selected_templates' => $data['selected_templates'] ?? null,
                'session_name' => $data['session_name'] ?? null,
                'session_start' => $data['session_start'] ?? null,
                'session_end' => $data['session_end'] ?? null,
            ], fn ($v) => $v !== null);

            $onboardingCompleted = (bool) ($data['onboarding_completed'] ?? false);
            $onboardingStep = (int) ($data['onboarding_step'] ?? ($onboardingCompleted ? 9 : 1));

            $tenant = Tenant::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'type' => $normalizedType,
                    'default_locale' => $data['default_locale'] ?? 'en',
                    'status' => 'active',
                    'onboarding_step' => $onboardingStep,
                    'onboarding_completed' => $onboardingCompleted,
                    'settings' => $settings,
                ]
            );

            // Set Postgres RLS and application context for subsequent tenant rows
            PostgresTenantContext::set($tenant->id);
            app(TenantContext::class)->setTenant($tenant);

            // 2. Ensure default SaaS subscription plan & active subscription exist for quota tracking
            (new SubscriptionPlanService())->seedDefaultPlans();
            $defaultPlanCode = $data['plan_code'] ?? 'growth';
            $defaultPlan = SubscriptionPlan::where('code', $defaultPlanCode)->first()
                ?? SubscriptionPlan::where('is_active', true)->orderBy('price_pkr')->first();

            if ($defaultPlan && !TenantSubscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
                TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $defaultPlan->id,
                    'status' => 'active',
                    'current_period_start' => now()->toDateString(),
                    'current_period_end' => now()->addMonth()->toDateString(),
                    'auto_renew' => true,
                ]);
            }

            // 3. Create Main Branch idempotently
            $branch = Branch::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'MAIN-01'],
                [
                    'name' => $data['branch_name'] ?? 'Main Campus',
                    'city' => $data['city'] ?? 'Karachi',
                    'address' => $data['address'] ?? null,
                    'phone' => $data['owner_phone'] ?? null,
                    'is_main' => true,
                    'status' => 'active',
                ]
            );

            app(TenantContext::class)->setBranch($branch);

            // 4. Create Default Subdomain Domain mapping idempotently
            $baseDomain = config('app.base_domain', 'educore.test');
            $fullDomain = "{$slug}.{$baseDomain}";

            Domain::updateOrCreate(
                ['domain' => $fullDomain],
                [
                    'tenant_id' => $tenant->id,
                    'is_primary' => true,
                    'is_verified' => true,
                ]
            );

            // 5. Create Institute Owner User idempotently
            $owner = User::updateOrCreate(
                ['email' => $data['owner_email']],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => $data['owner_name'],
                    'phone_number' => $data['owner_phone'] ?? null,
                    'password' => Hash::make($data['owner_password']),
                    'preferred_locale' => $tenant->default_locale,
                    'is_super_admin' => false,
                    'status' => 'active',
                ]
            );

            // 6. Create and assign Tenant-Scoped 'institute_owner' role
            $tenantPermissions = [
                'institute.view_settings',
                'institute.edit_settings',
                'branch.manage',
                'academics.manage',
                'students.view',
                'students.create',
                'students.edit',
                'students.delete',
                'attendance.mark',
                'attendance.view',
                'finance.view_vouchers',
                'finance.collect_fee',
                'finance.manage_structures',
                'finance.cashier_closing',
                'exams.manage',
                'exams.enter_marks',
                'reports.view',
            ];

            foreach ($tenantPermissions as $perm) {
                Permission::firstOrCreate([
                    'name' => $perm,
                    'guard_name' => 'web',
                ]);
            }

            $ownerRole = Role::firstOrCreate([
                'name' => 'institute_owner',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            $ownerRole->syncPermissions($tenantPermissions);
            setPermissionsTeamId($tenant->id);
            $owner->assignRole($ownerRole);

            // 7. Seed Academic Session & Fiscal Year if session details provided (Step 5)
            $session = null;
            if (!empty($data['session_name'])) {
                $startDate = $data['session_start'] ?? now()->startOfMonth()->toDateString();
                $endDate = $data['session_end'] ?? now()->addMonths(10)->endOfMonth()->toDateString();

                $session = AcademicSession::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $data['session_name']],
                    [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_current' => true,
                    ]
                );

                FiscalYear::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => 'FY ' . $data['session_name']],
                    [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'open',
                    ]
                );
            }

            // 8. Seed Financial Chart of Accounts & Default Fee Categories (Step 7)
            if (!empty($data['seed_financial_defaults']) || !empty($data['session_name'])) {
                (new GeneralLedgerService())->seedDefaultChartOfAccounts($tenant->id, $normalizedType);

                $tuitionRev = ChartOfAccount::where('tenant_id', $tenant->id)->where('account_code', '4010')->first();
                if ($tuitionRev) {
                    FeeCategory::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => 'TUITION'],
                        [
                            'name' => 'Monthly Tuition Fee',
                            'default_revenue_account_id' => $tuitionRev->id,
                            'is_active' => true,
                        ]
                    );
                }

                $courseRev = ChartOfAccount::where('tenant_id', $tenant->id)->where('account_code', '4015')->first();
                if ($courseRev && $tenant->isAcademy()) {
                    FeeCategory::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => 'COURSE_FEE'],
                        [
                            'name' => 'Academy Course Fee',
                            'default_revenue_account_id' => $courseRev->id,
                            'is_active' => true,
                        ]
                    );
                }
            }

            // 9. Seed Starter Cohort Templates into relational tables (Step 6)
            if (!empty($data['selected_templates']) && is_array($data['selected_templates'])) {
                $this->seedStarterTemplates(
                    $tenant,
                    $branch,
                    $data['selected_templates'],
                    $data['session_start'] ?? now()->toDateString(),
                    $data['session_end'] ?? now()->addMonths(6)->toDateString()
                );
            }

            // 10. Provision Invited Staff Users & Roles (Step 8)
            $invitedStaff = $this->provisionInvitedStaff($tenant, $branch, $data);

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'branch' => $branch,
                'session' => $session,
                'invited_staff' => $invitedStaff,
            ];
        });
    }

    /**
     * Create an additional branch for an existing tenant with strict server-side quota enforcement.
     */
    public function createBranch(Tenant $tenant, array $data): Branch
    {
        app(QuotaEnforcementService::class)->assertCanCreateBranch($tenant);

        return DB::transaction(function () use ($tenant, $data) {
            PostgresTenantContext::set($tenant->id);

            return Branch::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? ('BR-' . strtoupper(Str::random(4))),
                'city' => $data['city'] ?? 'Karachi',
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_main' => (bool) ($data['is_main'] ?? false),
                'status' => $data['status'] ?? 'active',
            ]);
        });
    }

    /**
     * Seed relational classes, sections, subjects, grading scales, or academy programs/courses/batches
     * from selected onboarding starter templates idempotently.
     */
    protected function seedStarterTemplates(
        Tenant $tenant,
        Branch $branch,
        array $selectedTemplates,
        string $startDate,
        string $endDate
    ): void {
        if ($tenant->isSchool()) {
            (new GradingScaleService())->seedDefaultFbiseScale($tenant->id);

            $defaultSubjects = [
                ['MATH', 'Mathematics', 'science'],
                ['ENG', 'English', 'languages'],
                ['SCI', 'General Science', 'science'],
            ];
            foreach ($defaultSubjects as [$code, $name, $subCategory]) {
                Subject::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    ['name' => $name, 'category' => $subCategory]
                );
            }
        }

        $schoolTemplateMap = [
            'Primary Section (Grades 1 to 5)' => [
                ['Grade 1', 1, 1],
                ['Grade 2', 2, 2],
                ['Grade 3', 3, 3],
                ['Grade 4', 4, 4],
                ['Grade 5', 5, 5],
            ],
            'Middle Section (Grades 6 to 8)' => [
                ['Grade 6', 6, 6],
                ['Grade 7', 7, 7],
                ['Grade 8', 8, 8],
            ],
            'Matriculation (Grades 9 & 10)' => [
                ['Grade 9', 9, 9],
                ['Grade 10', 10, 10],
            ],
            'Cambridge O-Levels (Years 1 to 3)' => [
                ['O-Level Year 1', 9, 11],
                ['O-Level Year 2', 10, 12],
                ['O-Level Year 3', 11, 13],
            ],
        ];

        $academyTemplateMap = [
            'MDCAT Comprehensive Test Prep' => ['entry_test', 'MDCAT Full Length Course', 25000.00, 16],
            'ECAT Engineering Entry Test' => ['entry_test', 'ECAT Mathematics & Physics', 22000.00, 16],
            'FSc / Intermediate Evening Coaching' => ['academic_coaching', 'FSc Part-I & II Evening Track', 15000.00, 24],
            'IELTS & Spoken English Fluency' => ['skill_development', 'IELTS Band 7+ Intensive', 18000.00, 8],
        ];

        foreach ($selectedTemplates as $templateName) {
            if (isset($schoolTemplateMap[$templateName]) && $tenant->isSchool()) {
                foreach ($schoolTemplateMap[$templateName] as [$className, $numericLevel, $displayOrder]) {
                    $schoolClass = SchoolClass::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'branch_id' => $branch->id,
                            'name' => $className,
                        ],
                        [
                            'numeric_level' => $numericLevel,
                            'display_order' => $displayOrder,
                        ]
                    );

                    SchoolSection::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'school_class_id' => $schoolClass->id,
                            'name' => 'Section A',
                        ],
                        ['max_capacity' => 35]
                    );
                }
                continue;
            }

            if (isset($academyTemplateMap[$templateName]) && $tenant->isAcademy()) {
                [$category, $courseName, $baseFee, $weeks] = $academyTemplateMap[$templateName];

                $program = AcademyProgram::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'name' => $templateName,
                    ],
                    ['category' => $category]
                );

                $course = AcademyCourse::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'academy_program_id' => $program->id,
                        'name' => $courseName,
                    ],
                    [
                        'base_fee' => $baseFee,
                        'duration_weeks' => $weeks,
                    ]
                );

                AcademyBatch::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'academy_course_id' => $course->id,
                        'name' => $courseName . ' - Batch 1',
                    ],
                    [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'max_capacity' => 40,
                        'status' => 'upcoming',
                    ]
                );
            }
        }
    }

    /**
     * Create invited Principal and Cashier staff accounts with scoped Spatie roles
     * and password-reset tokens (never using hardcoded passwords).
     *
     * @return array<string, User>
     */
    protected function provisionInvitedStaff(Tenant $tenant, Branch $branch, array $data): array
    {
        $created = [];
        setPermissionsTeamId($tenant->id);

        if (!empty($data['principal_email'])) {
            $principalRole = Role::firstOrCreate([
                'name' => 'principal',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $principalRole->syncPermissions([
                'institute.view_settings',
                'academics.manage',
                'students.view',
                'students.create',
                'students.edit',
                'attendance.mark',
                'attendance.view',
                'exams.manage',
                'exams.enter_marks',
                'reports.view',
            ]);

            $principalUser = User::updateOrCreate(
                ['email' => trim((string) $data['principal_email'])],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => !empty($data['principal_name']) ? trim((string) $data['principal_name']) : ($tenant->name . ' Principal'),
                    'password' => Hash::make(Str::random(32)),
                    'preferred_locale' => $tenant->default_locale,
                    'is_super_admin' => false,
                    'status' => 'active',
                ]
            );
            $principalUser->assignRole($principalRole);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $principalUser->email],
                ['token' => Hash::make(Str::random(64)), 'created_at' => now()]
            );

            $created['principal'] = $principalUser;
        }

        if (!empty($data['cashier_email'])) {
            $cashierRole = Role::firstOrCreate([
                'name' => 'cashier',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $cashierRole->syncPermissions([
                'students.view',
                'finance.view_vouchers',
                'finance.collect_fee',
                'finance.cashier_closing',
                'reports.view',
            ]);

            $cashierUser = User::updateOrCreate(
                ['email' => trim((string) $data['cashier_email'])],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => !empty($data['cashier_name']) ? trim((string) $data['cashier_name']) : ($tenant->name . ' Cashier'),
                    'password' => Hash::make(Str::random(32)),
                    'preferred_locale' => $tenant->default_locale,
                    'is_super_admin' => false,
                    'status' => 'active',
                ]
            );
            $cashierUser->assignRole($cashierRole);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $cashierUser->email],
                ['token' => Hash::make(Str::random(64)), 'created_at' => now()]
            );

            $created['cashier'] = $cashierUser;
        }

        return $created;
    }
}
