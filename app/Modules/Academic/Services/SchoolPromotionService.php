<?php

namespace App\Modules\Academic\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolPromotion;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SchoolPromotionService
{
    /**
     * Promote or retain a student from one academic session/section to another.
     */
    public function promote(
        Student $student,
        AcademicSession $fromSession,
        AcademicSession $toSession,
        SchoolSection $fromSection,
        SchoolSection $toSection,
        string $resultSummary = 'promoted',
        ?User $promotedBy = null,
        ?string $remarks = null
    ): SchoolPromotion {
        return DB::transaction(function () use (
            $student,
            $fromSession,
            $toSession,
            $fromSection,
            $toSection,
            $resultSummary,
            $promotedBy,
            $remarks
        ) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $student->tenant_id;

            // 1. Locate and close previous enrollment
            $currentEnrollment = SchoolStudentEnrollment::where('tenant_id', $tenantId)
                ->where('student_id', $student->id)
                ->where('academic_session_id', $fromSession->id)
                ->where('school_section_id', $fromSection->id)
                ->first();

            if ($currentEnrollment) {
                $currentEnrollment->update([
                    'enrollment_status' => $resultSummary,
                ]);
            }

            // 2. Check if student already enrolled in target session
            $existingTarget = SchoolStudentEnrollment::where('tenant_id', $tenantId)
                ->where('student_id', $student->id)
                ->where('academic_session_id', $toSession->id)
                ->first();

            if ($existingTarget) {
                throw new InvalidArgumentException("Student is already enrolled in target session [{$toSession->name}].");
            }

            // 3. Create new enrollment in target section
            SchoolStudentEnrollment::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'academic_session_id' => $toSession->id,
                'school_section_id' => $toSection->id,
                'enrollment_status' => 'active',
                'enrolled_at' => now()->toDateString(),
            ]);

            // 4. Create promotion audit record
            return SchoolPromotion::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'from_session_id' => $fromSession->id,
                'to_session_id' => $toSession->id,
                'from_section_id' => $fromSection->id,
                'to_section_id' => $toSection->id,
                'result_summary' => $resultSummary,
                'promoted_by_user_id' => $promotedBy?->id,
                'promoted_at' => now(),
                'remarks' => $remarks,
            ]);
        });
    }
}

