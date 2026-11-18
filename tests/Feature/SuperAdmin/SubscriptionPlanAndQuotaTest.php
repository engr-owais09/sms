<?php

namespace Tests\Feature\SuperAdmin;

use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\QuotaEnforcementService;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_seed_default_plans_and_enforce_branch_and_student_quotas(): void
    {
        $plans = (new SubscriptionPlanService())->seedDefaultPlans();
        $this->assertCount(3, $plans);

        $starter = SubscriptionPlan::where('code', 'starter')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Test School Quota',
            'slug' => 'quota-school-1',
            'type' => 'school',
            'status' => 'active',
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'active',
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'custom_max_students' => 1, // Override for test
        ]);

        $quota = new QuotaEnforcementService();

        // Feature gates
        $this->assertTrue($quota->hasFeature($tenant, 'finance'));
        $this->assertFalse($quota->hasFeature($tenant, 'sms_alerts'));

        // Branch quota (max_branches = 1)
        $quota->assertCanCreateBranch($tenant);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'M-1',
            'city' => 'Lahore',
        ]);

        try {
            $quota->assertCanCreateBranch($tenant);
            $this->fail('Expected QuotaExceededException for branch limit.');
        } catch (QuotaExceededException $e) {
            $this->assertStringContainsString('Branch quota exceeded', $e->getMessage());
        }

        // Student quota (custom_max_students = 1)
        $quota->assertCanAdmitStudent($tenant);
        Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => 'Q-01',
            'first_name' => 'Amina',
            'last_name' => 'Bibi',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessage('Active student quota exceeded');
        $quota->assertCanAdmitStudent($tenant);
    }
}
