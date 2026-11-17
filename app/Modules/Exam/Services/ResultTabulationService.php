<?php

namespace App\Modules\Exam\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Exam\Models\ExamMark;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Models\GradingScale;
use Illuminate\Support\Collection;
use RuntimeException;

class ResultTabulationService
{
    protected GradingScaleService $gradingService;

    public function __construct(?GradingScaleService $gradingService = null)
    {
        $this->gradingService = $gradingService ?? new GradingScaleService();
    }

    public function tabulateSectionResults(
        ExamTerm $term,
        SchoolSection $section,
        bool $publish = false
    ): Collection {
        return TenantTransactionManager::run(function () use ($term, $section, $publish) {
            $papers = ExamPaper::where('tenant_id', $term->tenant_id)
                ->where('exam_term_id', $term->id)
                ->where('school_class_id', $section->school_class_id)
                ->get();

            if ($papers->isEmpty()) {
                throw new RuntimeException("No exam papers configured for class in term '{$term->name}'.");
            }

            $scale = $term->gradingScale
                ?? GradingScale::where('tenant_id', $term->tenant_id)->where('is_default', true)->first()
                ?? $this->gradingService->seedDefaultFbiseScale($term->tenant_id);

            $enrollments = SchoolStudentEnrollment::where('tenant_id', $term->tenant_id)
                ->where('academic_session_id', $term->academic_session_id)
                ->where('school_section_id', $section->id)
                ->where('enrollment_status', 'active')
                ->get();

            $totalMax = round((float) $papers->sum('max_marks'), 2);
            $paperIds = $papers->pluck('id');
            $marksByStudent = ExamMark::whereIn('exam_paper_id', $paperIds)
                ->where('school_section_id', $section->id)
                ->get()
                ->groupBy('student_id');

            $computed = [];
            foreach ($enrollments as $enrollment) {
                $studentId = $enrollment->student_id;
                $studentMarks = ($marksByStudent->get($studentId) ?? collect())->keyBy('exam_paper_id');

                $obtained = 0.00;
                $failedSubjects = 0;

                foreach ($papers as $paper) {
                    $mark = $studentMarks->get($paper->id);
                    $score = ($mark && !$mark->is_absent) ? (float) $mark->marks_obtained : 0.00;
                    $obtained += $score;

                    if (!$mark || $mark->is_absent || $score < (float) $paper->passing_marks) {
                        $failedSubjects++;
                    }
                }

                $obtained = round($obtained, 2);
                $pct = $totalMax > 0 ? round(($obtained / $totalMax) * 100, 2) : 0.00;
                $gradeInfo = $this->gradingService->resolveGrade($scale, $pct);

                $resultStatus = 'pass';
                if ($failedSubjects > 0 || !$gradeInfo['is_passing']) {
                    $resultStatus = ($failedSubjects === 1 && $gradeInfo['is_passing']) ? 'compartment' : 'fail';
                }

                $computed[] = [
                    'student_id' => $studentId,
                    'total_max_marks' => $totalMax,
                    'total_obtained_marks' => $obtained,
                    'percentage' => $pct,
                    'grade_letter' => $gradeInfo['grade_letter'],
                    'gpa' => $gradeInfo['gpa_points'],
                    'result_status' => $resultStatus,
                ];
            }

            // Sort descending by total_obtained_marks for dense ranking
            usort($computed, fn ($a, $b) => $b['total_obtained_marks'] <=> $a['total_obtained_marks']);

            $rank = 0;
            $lastScore = null;
            $savedResults = collect();
            $publishedAt = $publish ? now() : null;

            foreach ($computed as $row) {
                if ($lastScore === null || $row['total_obtained_marks'] < $lastScore) {
                    $rank++;
                    $lastScore = $row['total_obtained_marks'];
                }

                $result = ExamResult::updateOrCreate(
                    [
                        'tenant_id' => $term->tenant_id,
                        'exam_term_id' => $term->id,
                        'student_id' => $row['student_id'],
                    ],
                    [
                        'school_section_id' => $section->id,
                        'total_max_marks' => $row['total_max_marks'],
                        'total_obtained_marks' => $row['total_obtained_marks'],
                        'percentage' => $row['percentage'],
                        'grade_letter' => $row['grade_letter'],
                        'gpa' => $row['gpa'],
                        'section_rank' => $rank,
                        'result_status' => $row['result_status'],
                        'published_at' => $publishedAt,
                    ]
                );

                $savedResults->push($result);
            }

            if ($publish) {
                $term->update(['status' => 'published']);
            }

            return $savedResults;
        });
    }

    public function computeWeightedCumulativePercentage(
        string $tenantId,
        string $academicSessionId,
        string $studentId
    ): array {
        $results = ExamResult::with('examTerm')
            ->where('tenant_id', $tenantId)
            ->where('student_id', $studentId)
            ->whereHas('examTerm', fn ($q) => $q->where('academic_session_id', $academicSessionId))
            ->get();

        $totalWeight = 0.00;
        $weightedSum = 0.00;

        foreach ($results as $res) {
            $w = (float) ($res->examTerm->weightage_percentage ?? 0);
            $weightedSum += ((float) $res->percentage * $w);
            $totalWeight += $w;
        }

        return [
            'terms_count' => $results->count(),
            'total_weightage' => round($totalWeight, 2),
            'cumulative_percentage' => $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.00,
        ];
    }
}
