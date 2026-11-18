<?php

namespace App\Modules\SuperAdmin\Services;

use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use Illuminate\Support\Collection;

class SubscriptionPlanService
{
    public function seedDefaultPlans(): Collection
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter Campus',
                'description' => 'Single-campus school or academy plan up to 250 students.',
                'billing_cycle' => 'monthly',
                'price_pkr' => 15000.00,
                'per_student_rate_pkr' => 0.00,
                'max_branches' => 1,
                'max_students' => 250,
                'max_staff' => 30,
                'features_json' => [
                    'finance' => true,
                    'attendance' => true,
                    'exams' => true,
                    'bilingual_pdf' => true,
                    'sms_alerts' => false,
                ],
                'is_active' => true,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth Multi-Branch',
                'description' => 'Growing network up to 5 campuses and 1,500 active students.',
                'billing_cycle' => 'monthly',
                'price_pkr' => 35000.00,
                'per_student_rate_pkr' => 50.00,
                'max_branches' => 5,
                'max_students' => 1500,
                'max_staff' => 150,
                'features_json' => [
                    'finance' => true,
                    'attendance' => true,
                    'exams' => true,
                    'bilingual_pdf' => true,
                    'sms_alerts' => true,
                ],
                'is_active' => true,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise Network',
                'description' => 'Flagship school/academy chain with high-volume quotas.',
                'billing_cycle' => 'yearly',
                'price_pkr' => 360000.00,
                'per_student_rate_pkr' => 35.00,
                'max_branches' => 50,
                'max_students' => 25000,
                'max_staff' => 2500,
                'features_json' => [
                    'finance' => true,
                    'attendance' => true,
                    'exams' => true,
                    'bilingual_pdf' => true,
                    'sms_alerts' => true,
                ],
                'is_active' => true,
            ],
        ];

        $result = collect();
        foreach ($plans as $planData) {
            $result->push(
                SubscriptionPlan::firstOrCreate(['code' => $planData['code']], $planData)
            );
        }

        return $result;
    }
}
