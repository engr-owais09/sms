<?php

namespace App\Modules\Finance\Jobs;

use App\Core\Tenancy\TenantContext;
use App\Modules\Finance\Exceptions\DuplicateInvoiceException;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Student\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSchoolMonthlyVouchersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $branchId,
        public string $classId,
        public string $billingPeriod,
        public string $issueDate,
        public string $dueDate,
        public ?string $sessionId = null
    ) {
    }

    public function handle(InvoiceGenerationService $generationService): void
    {
        app(TenantContext::class)->setTenantId($this->tenantId);

        // Find active enrolled students in this class
        $studentsQuery = Student::withoutGlobalScopes()
            ->join('school_student_enrollments', 'students.id', '=', 'school_student_enrollments.student_id')
            ->join('school_sections', 'school_student_enrollments.school_section_id', '=', 'school_sections.id')
            ->where('students.tenant_id', $this->tenantId)
            ->where('students.branch_id', $this->branchId)
            ->where('students.status', 'active')
            ->where('school_student_enrollments.enrollment_status', 'active')
            ->where('school_sections.school_class_id', $this->classId)
            ->select('students.*');

        if ($this->sessionId !== null) {
            $studentsQuery->where('school_student_enrollments.academic_session_id', $this->sessionId);
        }

        $students = $studentsQuery->get();

        foreach ($students as $student) {
            try {
                $generationService->generateSchoolMonthlyInvoice(
                    $student,
                    $this->billingPeriod,
                    $this->issueDate,
                    $this->dueDate,
                    $this->sessionId,
                    $this->classId
                );
            } catch (DuplicateInvoiceException $e) {
                // Idempotent: already generated, skip safely
                Log::info("Skipping duplicate monthly invoice: " . $e->getMessage());
            } catch (\Throwable $e) {
                Log::error("Failed to generate monthly invoice for student {$student->id}: " . $e->getMessage());
            }
        }
    }
}

