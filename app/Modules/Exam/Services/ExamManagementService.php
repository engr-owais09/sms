<?php

namespace App\Modules\Exam\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Exam\Models\ExamMark;
use App\Modules\Exam\Models\ExamPaper;
use InvalidArgumentException;
use RuntimeException;

class ExamManagementService
{
    /**
     * Record or update marks for students in an exam paper with strict bounds validation.
     *
     * @param array<string, array{marks?: float, is_absent?: bool, remarks?: string}> $entries
     */
    public function recordBulkMarks(
        ExamPaper $paper,
        string $sectionId,
        array $entries,
        bool $submit = true,
        ?User $actor = null
    ): void {
        $paper->loadMissing('examTerm');
        if ($paper->examTerm && $paper->examTerm->status === 'locked') {
            throw new RuntimeException("Exam term '{$paper->examTerm->name}' is locked.");
        }

        TenantTransactionManager::run(function () use ($paper, $sectionId, $entries, $submit, $actor) {
            $maxMarks = (float) $paper->max_marks;

            foreach ($entries as $studentId => $payload) {
                $isAbsent = (bool) ($payload['is_absent'] ?? false);
                $marks = $isAbsent ? 0.00 : round((float) ($payload['marks'] ?? 0.00), 2);

                if ($marks < 0 || $marks > $maxMarks) {
                    throw new InvalidArgumentException("Marks ({$marks}) must be between 0 and {$maxMarks}.");
                }

                ExamMark::updateOrCreate(
                    [
                        'tenant_id' => $paper->tenant_id,
                        'exam_paper_id' => $paper->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'school_section_id' => $sectionId,
                        'marks_obtained' => $marks,
                        'is_absent' => $isAbsent,
                        'remarks' => $payload['remarks'] ?? null,
                        'entered_by_user_id' => $actor?->id,
                        'status' => $submit ? 'submitted' : 'draft',
                    ]
                );
            }
        });
    }
}
