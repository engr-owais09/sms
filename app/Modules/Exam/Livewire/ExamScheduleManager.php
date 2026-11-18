<?php

namespace App\Modules\Exam\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Institute\Models\Branch;
use Livewire\Component;

class ExamScheduleManager extends Component
{
    public string $termName = '';
    public string $termType = 'midterm';
    public float $weightagePercentage = 100.00;
    public string $startDate = '';
    public string $endDate = '';

    public ?string $selectedTermId = null;
    public ?string $paperClassId = null;
    public ?string $paperSubjectId = null;
    public string $examDate = '';
    public float $maxMarks = 100.00;
    public float $passingMarks = 40.00;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addDays(10)->toDateString();
        $this->examDate = now()->addDay()->toDateString();

        if (!$tenant) {
            return;
        }

        $this->selectedTermId = ExamTerm::where('tenant_id', $tenant->id)->latest()->value('id');
        $this->paperClassId = SchoolClass::where('tenant_id', $tenant->id)->value('id');
        $this->paperSubjectId = Subject::where('tenant_id', $tenant->id)->value('id');
    }

    public function createTerm(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();
        $session = AcademicSession::where('tenant_id', $tenant->id)->where('is_current', true)->first();

        if (!$this->termName || !$session) {
            $this->errorMessage = 'Exam Term name and active Academic Session are required.';
            return;
        }

        try {
            $scale = (new GradingScaleService())->seedDefaultFbiseScale($tenant->id);
            $term = ExamTerm::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'academic_session_id' => $session->id,
                'grading_scale_id' => $scale->id,
                'name' => trim($this->termName),
                'term_type' => $this->termType,
                'weightage_percentage' => $this->weightagePercentage,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'status' => 'scheduled',
            ]);

            $this->selectedTermId = $term->id;
            $this->termName = '';
            $this->successMessage = "Exam term '{$term->name}' created successfully!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function addPaper(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $tenant = app(TenantContext::class)->getTenant();

        if (!$this->selectedTermId || !$this->paperClassId || !$this->paperSubjectId) {
            $this->errorMessage = 'Select Exam Term, Class, and Subject.';
            return;
        }

        try {
            ExamPaper::create([
                'tenant_id' => $tenant->id,
                'exam_term_id' => $this->selectedTermId,
                'school_class_id' => $this->paperClassId,
                'subject_id' => $this->paperSubjectId,
                'exam_date' => $this->examDate,
                'max_marks' => $this->maxMarks,
                'passing_marks' => $this->passingMarks,
            ]);

            $this->successMessage = 'Exam paper scheduled successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $terms = $tenant ? ExamTerm::where('tenant_id', $tenant->id)->latest()->get() : collect();
        $classes = $tenant ? SchoolClass::where('tenant_id', $tenant->id)->get() : collect();
        $subjects = $tenant ? Subject::where('tenant_id', $tenant->id)->get() : collect();
        $papers = ($tenant && $this->selectedTermId)
            ? ExamPaper::with(['schoolClass', 'subject'])->where('exam_term_id', $this->selectedTermId)->orderBy('exam_date')->get()
            : collect();

        return view('livewire.exam.exam-schedule-manager', [
            'terms' => $terms,
            'classes' => $classes,
            'subjects' => $subjects,
            'papers' => $papers,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
