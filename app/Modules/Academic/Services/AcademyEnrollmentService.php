<?php

namespace App\Modules\Academic\Services;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcademyEnrollmentService
{
    /**
     * Enroll a student into an academy batch.
     */
    public function enroll(
        Student $student,
        AcademyBatch $batch,
        ?float $agreedFee = null,
        string $feeScheduleType = 'fixed_course_fee'
    ): AcademyBatchEnrollment {
        return DB::transaction(function () use ($student, $batch, $agreedFee, $feeScheduleType) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $student->tenant_id;

            // 1. Check capacity
            if ($batch->availableSeats() <= 0) {
                throw new InvalidArgumentException("Batch [{$batch->name}] has reached full capacity.");
            }

            // 2. Check if already enrolled in this specific batch
            $existing = AcademyBatchEnrollment::where('tenant_id', $tenantId)
                ->where('student_id', $student->id)
                ->where('academy_batch_id', $batch->id)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException("Student is already enrolled in batch [{$batch->name}].");
            }

            $fee = $agreedFee ?? $batch->course->base_fee;

            return AcademyBatchEnrollment::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'academy_batch_id' => $batch->id,
                'fee_schedule_type' => $feeScheduleType,
                'agreed_fee_amount' => $fee,
                'enrollment_status' => 'active',
                'enrolled_at' => now()->toDateString(),
            ]);
        });
    }
}

