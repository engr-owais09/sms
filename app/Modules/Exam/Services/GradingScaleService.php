<?php

namespace App\Modules\Exam\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Exam\Models\GradingScale;
use App\Modules\Exam\Models\GradingScaleBand;

class GradingScaleService
{
    public function seedDefaultFbiseScale(string $tenantId): GradingScale
    {
        return TenantTransactionManager::run(function () use ($tenantId) {
            $scale = GradingScale::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'FBISE / Matric Standard'],
                ['scale_type' => 'percentage', 'is_default' => true]
            );

            $bands = [
                ['A+', 90.00, 100.00, 4.00, 'Outstanding', true],
                ['A', 80.00, 89.99, 3.70, 'Excellent', true],
                ['B', 70.00, 79.99, 3.20, 'Very Good', true],
                ['C', 60.00, 69.99, 2.70, 'Good', true],
                ['D', 50.00, 59.99, 2.20, 'Fair', true],
                ['E', 40.00, 49.99, 1.50, 'Satisfactory', true],
                ['F', 0.00, 39.99, 0.00, 'Fail', false],
            ];

            foreach ($bands as [$letter, $min, $max, $gpa, $remarks, $passing]) {
                GradingScaleBand::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'grading_scale_id' => $scale->id,
                        'grade_letter' => $letter,
                    ],
                    [
                        'min_percentage' => $min,
                        'max_percentage' => $max,
                        'gpa_points' => $gpa,
                        'remarks' => $remarks,
                        'is_passing' => $passing,
                    ]
                );
            }

            return $scale->fresh('bands');
        });
    }

    public function resolveGrade(GradingScale $scale, float $percentage): array
    {
        $pct = round(max(0.00, min(100.00, $percentage)), 2);

        $band = $scale->bands()
            ->where('min_percentage', '<=', $pct)
            ->where('max_percentage', '>=', $pct)
            ->first();

        if (!$band) {
            return [
                'grade_letter' => $pct >= 40.00 ? 'P' : 'F',
                'gpa_points' => null,
                'remarks' => null,
                'is_passing' => $pct >= 40.00,
            ];
        }

        return [
            'grade_letter' => $band->grade_letter,
            'gpa_points' => $band->gpa_points !== null ? (float) $band->gpa_points : null,
            'remarks' => $band->remarks,
            'is_passing' => (bool) $band->is_passing,
        ];
    }
}
