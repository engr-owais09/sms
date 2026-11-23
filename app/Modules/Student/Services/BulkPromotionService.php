<?php

namespace App\Modules\Student\Services;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;

class BulkPromotionService
{
    /**
     * Promote multiple tenant-owned students into a target session/section transactionally.
     *
     * @param array<int, string> $studentIds
     */
    public function promote(
        Tenant $tenant,
        array $studentIds,
        AcademicSession $targetSession,
        SchoolSection $targetSection,
        ?int $actorUserId = null
    ): int {
        $promoted = 0;

        TenantTransactionManager::run(function () use ($tenant, $studentIds, $targetSession, $targetSection, &$promoted): void {
            $students = Student::where('tenant_id', $tenant->id)
                ->whereIn('id', $studentIds)
                ->get();

            foreach ($students as $student) {
                SchoolStudentEnrollment::where('tenant_id', $tenant->id)
                    ->where('student_id', $student->id)
                    ->where('enrollment_status', 'active')
                    ->update(['enrollment_status' => 'promoted']);

                SchoolStudentEnrollment::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'student_id' => $student->id,
                        'academic_session_id' => $targetSession->id,
                        'school_section_id' => $targetSection->id,
                    ],
                    [
                        'roll_number' => (string) ($promoted + 1),
                        'enrolled_at' => now()->toDateString(),
                        'enrollment_status' => 'active',
                    ]
                );

                $promoted++;
            }
        });

        TenantAuditService::record(
            'students.bulk_promoted',
            Student::class,
            null,
            ['count' => $promoted, 'target_session_id' => $targetSession->id, 'target_section_id' => $targetSection->id],
            $tenant->id,
            $actorUserId
        );

        return $promoted;
    }
}
