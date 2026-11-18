<?php

namespace App\Modules\Exam\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Exam\Models\AcademyAssessment;
use App\Modules\Exam\Models\AcademyAssessmentScore;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AcademyAssessmentService
{
    public function recordAndRankScores(
        AcademyAssessment $assessment,
        array $entries,
        bool $publish = true
    ): Collection {
        return TenantTransactionManager::run(function () use ($assessment, $entries, $publish) {
            $maxMarks = (float) $assessment->max_marks;
            $prepared = [];

            foreach ($entries as $studentId => $payload) {
                $isAbsent = (bool) ($payload['is_absent'] ?? false);
                $marks = $isAbsent ? 0.00 : round((float) ($payload['marks'] ?? 0.00), 2);

                if ($marks < 0 || $marks > $maxMarks) {
                    throw new InvalidArgumentException("Marks ({$marks}) must be between 0 and {$maxMarks}.");
                }

                $pct = $maxMarks > 0 ? round(($marks / $maxMarks) * 100, 2) : 0.00;

                $prepared[] = [
                    'student_id' => $studentId,
                    'marks_obtained' => $marks,
                    'is_absent' => $isAbsent,
                    'percentage' => $pct,
                    'remarks' => $payload['remarks'] ?? null,
                ];
            }

            usort($prepared, fn ($a, $b) => $b['marks_obtained'] <=> $a['marks_obtained']);

            $rank = 0;
            $lastScore = null;
            $saved = collect();

            foreach ($prepared as $row) {
                if ($lastScore === null || $row['marks_obtained'] < $lastScore) {
                    $rank++;
                    $lastScore = $row['marks_obtained'];
                }

                $score = AcademyAssessmentScore::updateOrCreate(
                    [
                        'tenant_id' => $assessment->tenant_id,
                        'academy_assessment_id' => $assessment->id,
                        'student_id' => $row['student_id'],
                    ],
                    [
                        'marks_obtained' => $row['marks_obtained'],
                        'is_absent' => $row['is_absent'],
                        'percentage' => $row['percentage'],
                        'rank' => $rank,
                        'remarks' => $row['remarks'],
                    ]
                );

                $saved->push($score);
            }

            if ($publish) {
                $assessment->update(['status' => 'published']);
            }

            return $saved;
        });
    }
}
