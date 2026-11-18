<?php

namespace App\Modules\Exam\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Exam\Models\ExamMark;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\ExamManagementService;
use Livewire\Component;

class MarksEntryConsole extends Component
{
    public ?string $selectedTermId = null;
    public ?string $selectedPaperId = null;
    public ?string $selectedSectionId = null;

    public array $markRows = [];
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return;
        }

        $this->selectedTermId = ExamTerm::where('tenant_id', $tenant->id)->latest()->value('id');
        if ($this->selectedTermId) {
            $this->selectedPaperId = ExamPaper::where('exam_term_id', $this->selectedTermId)->value('id');
        }
        $this->selectedSectionId = SchoolSection::where('tenant_id', $tenant->id)->value('id');

        $this->loadStudents();
    }

    public function loadStudents(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->markRows = [];

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || !$this->selectedPaperId || !$this->selectedSectionId) {
            return;
        }

        $paper = ExamPaper::with('examTerm')->find($this->selectedPaperId);
        if (!$paper || !$paper->examTerm) {
            return;
        }

        $enrollments = SchoolStudentEnrollment::with('student')
            ->where('tenant_id', $tenant->id)
            ->where('academic_session_id', $paper->examTerm->academic_session_id)
            ->where('school_section_id', $this->selectedSectionId)
            ->where('enrollment_status', 'active')
            ->get();

        $existingMarks = ExamMark::where('exam_paper_id', $paper->id)
            ->where('school_section_id', $this->selectedSectionId)
            ->get()
            ->keyBy('student_id');

        foreach ($enrollments as $enr) {
            $stu = $enr->student;
            $existing = $existingMarks->get($stu->id);

            $this->markRows[] = [
                'student_id' => $stu->id,
                'admission_number' => $stu->admission_number,
                'full_name' => trim($stu->first_name . ' ' . $stu->last_name),
                'marks' => $existing ? (float) $existing->marks_obtained : 0.00,
                'is_absent' => $existing ? (bool) $existing->is_absent : false,
                'remarks' => $existing?->remarks ?? '',
            ];
        }
    }

    public function saveMarks(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (!$this->selectedPaperId || !$this->selectedSectionId || empty($this->markRows)) {
            $this->errorMessage = 'Select a paper and section with enrolled students.';
            return;
        }

        $actor = auth()->user() ?? User::first();
        $entries = [];
        foreach ($this->markRows as $row) {
            $entries[$row['student_id']] = [
                'marks' => (float) $row['marks'],
                'is_absent' => (bool) $row['is_absent'],
                'remarks' => $row['remarks'] ?: null,
            ];
        }

        try {
            $paper = ExamPaper::findOrFail($this->selectedPaperId);
            app(ExamManagementService::class)->recordBulkMarks($paper, $this->selectedSectionId, $entries, true, $actor);
            $this->successMessage = 'Marks saved and submitted successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $terms = $tenant ? ExamTerm::where('tenant_id', $tenant->id)->latest()->get() : collect();
        $papers = ($tenant && $this->selectedTermId)
            ? ExamPaper::with(['schoolClass', 'subject'])->where('exam_term_id', $this->selectedTermId)->get()
            : collect();
        $sections = $tenant ? SchoolSection::with('schoolClass')->where('tenant_id', $tenant->id)->get() : collect();

        return view('livewire.exam.marks-entry-console', [
            'terms' => $terms,
            'papers' => $papers,
            'sections' => $sections,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
