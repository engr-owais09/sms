<?php

namespace App\Modules\Exam\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Exam\Models\AcademyAssessment;
use App\Modules\Exam\Models\AcademyAssessmentScore;
use App\Modules\Exam\Services\AcademyAssessmentService;
use App\Modules\Institute\Models\Branch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;

class AcademyAssessmentManager extends Component
{
    public ?string $selectedBatchId = null;
    public ?string $selectedAssessmentId = null;

    // Create Assessment Fields
    public string $title = '';
    public string $assessmentType = 'chapter_test'; // chapter_test | mock_exam | past_paper | final_test
    public string $assessmentDate = '';
    public float $maxMarks = 100.00;
    public float $passingMarks = 40.00;

    // Student Score Entry Rows
    public array $scoreRows = [];

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->assessmentDate = now()->toDateString();
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return;
        }

        $this->selectedBatchId = AcademyBatch::where('tenant_id', $tenant->id)->value('id');
        if ($this->selectedBatchId) {
            $this->selectedAssessmentId = AcademyAssessment::where('tenant_id', $tenant->id)
                ->where('academy_batch_id', $this->selectedBatchId)
                ->latest('assessment_date')
                ->value('id');
        }

        if ($this->selectedAssessmentId) {
            $this->loadScoreRows();
        }
    }

    protected function authorizeActor(string $permission = 'exams.manage'): User
    {
        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$actor->isSuperAdmin() && (!$tenant || $actor->tenant_id !== $tenant->id)) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        if (!$actor->isSuperAdmin()) {
            if (!$actor->can($permission)) {
                throw new AuthorizationException("Missing required exam permission [{$permission}].");
            }
        }

        return $actor;
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function updatedSelectedAssessmentId(): void
    {
        $this->loadScoreRows();
    }

    public function createAssessment(): void
    {
        $this->resetFeedback();
        $this->authorizeActor('exams.manage');

        $this->validate([
            'selectedBatchId' => 'required|uuid',
            'title' => 'required|string|min:3|max:150',
            'assessmentType' => 'required|in:chapter_test,mock_exam,past_paper,final_test',
            'assessmentDate' => 'required|date',
            'maxMarks' => 'required|numeric|gt:0',
            'passingMarks' => 'required|numeric|min:0|lte:maxMarks',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $batch = AcademyBatch::where('tenant_id', $tenant->id)->find($this->selectedBatchId);
            if (!$batch) {
                $this->errorMessage = 'Selected academy batch does not belong to this workspace.';
                return;
            }

            $assessment = TenantTransactionManager::run(function () use ($tenant, $branch, $batch) {
                return AcademyAssessment::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $batch->branch_id ?? $branch->id,
                    'academy_batch_id' => $batch->id,
                    'title' => trim($this->title),
                    'assessment_type' => $this->assessmentType,
                    'assessment_date' => $this->assessmentDate,
                    'max_marks' => $this->maxMarks,
                    'passing_marks' => $this->passingMarks,
                    'status' => 'draft',
                ]);
            });

            $this->title = '';
            $this->selectedAssessmentId = $assessment->id;
            $this->loadScoreRows();
            $this->successMessage = "Academy assessment '{$assessment->title}' created successfully!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function loadScoreRows(): void
    {
        $this->scoreRows = [];
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || !$this->selectedAssessmentId) {
            return;
        }

        $assessment = AcademyAssessment::where('tenant_id', $tenant->id)->find($this->selectedAssessmentId);
        if (!$assessment) {
            return;
        }

        $enrollments = AcademyBatchEnrollment::with('student')
            ->where('tenant_id', $tenant->id)
            ->where('academy_batch_id', $assessment->academy_batch_id)
            ->where('enrollment_status', 'active')
            ->get();

        $existingScores = AcademyAssessmentScore::where('tenant_id', $tenant->id)
            ->where('academy_assessment_id', $assessment->id)
            ->get()
            ->keyBy('student_id');

        foreach ($enrollments as $enr) {
            $stu = $enr->student;
            if (!$stu) {
                continue;
            }
            $score = $existingScores->get($stu->id);

            $this->scoreRows[] = [
                'student_id' => $stu->id,
                'admission_number' => $stu->admission_number,
                'full_name' => $stu->full_name,
                'marks' => $score ? (float) $score->marks_obtained : 0.00,
                'is_absent' => $score ? (bool) $score->is_absent : false,
                'percentage' => $score ? (float) $score->percentage : 0.00,
                'rank' => $score?->rank,
                'remarks' => $score?->remarks ?? '',
            ];
        }
    }

    public function saveScores(bool $publish = true): void
    {
        $this->resetFeedback();
        // Publishing rankings is a managerial action; draft score entry only needs mark-entry rights.
        $this->authorizeActor($publish ? 'exams.manage' : 'exams.enter_marks');

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || !$this->selectedAssessmentId || empty($this->scoreRows)) {
            $this->errorMessage = 'Select an assessment with enrolled batch students first.';
            return;
        }

        try {
            $assessment = AcademyAssessment::where('tenant_id', $tenant->id)->findOrFail($this->selectedAssessmentId);

            // scoreRows is client-mutable Livewire state: only actively enrolled students of THIS batch may be scored.
            $eligibleStudentIds = AcademyBatchEnrollment::where('tenant_id', $tenant->id)
                ->where('academy_batch_id', $assessment->academy_batch_id)
                ->where('enrollment_status', 'active')
                ->pluck('student_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $entries = [];
            foreach ($this->scoreRows as $row) {
                $studentId = (string) ($row['student_id'] ?? '');
                if (!in_array($studentId, $eligibleStudentIds, true)) {
                    throw new \InvalidArgumentException('Scores can only be recorded for students actively enrolled in this batch.');
                }

                $isAbsent = (bool) ($row['is_absent'] ?? false);
                $marks = $row['marks'] ?? 0;
                if (!$isAbsent && (!is_numeric($marks) || !is_finite((float) $marks))) {
                    throw new \InvalidArgumentException('Marks must be a valid number.');
                }

                $entries[$studentId] = [
                    'marks' => $isAbsent ? 0.0 : (float) $marks,
                    'is_absent' => $isAbsent,
                    'remarks' => !empty($row['remarks']) ? mb_substr((string) $row['remarks'], 0, 255) : null,
                ];
            }

            app(AcademyAssessmentService::class)->recordAndRankScores($assessment, $entries, $publish);
            $this->loadScoreRows();
            $this->successMessage = "Assessment scores saved, ranked, and " . ($publish ? 'published' : 'saved as draft') . "!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $batches = $tenant
            ? AcademyBatch::with('course')->where('tenant_id', $tenant->id)->get()
            : collect();

        $assessments = $tenant
            ? AcademyAssessment::with('batch')
                ->where('tenant_id', $tenant->id)
                ->when($this->selectedBatchId, fn ($q) => $q->where('academy_batch_id', $this->selectedBatchId))
                ->latest('assessment_date')
                ->get()
            : collect();

        $activeAssessment = ($tenant && $this->selectedAssessmentId)
            ? AcademyAssessment::with(['batch.course', 'scores.student'])
                ->where('tenant_id', $tenant->id)
                ->find($this->selectedAssessmentId)
            : null;

        return view('livewire.exam.academy-assessment-manager', [
            'tenant' => $tenant,
            'batches' => $batches,
            'assessments' => $assessments,
            'activeAssessment' => $activeAssessment,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
