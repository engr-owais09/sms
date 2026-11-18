<?php

namespace App\Modules\Exam\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\ResultTabulationService;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultTabulationPortal extends Component
{
    public ?string $selectedTermId = null;
    public ?string $selectedSectionId = null;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return;
        }

        $this->selectedTermId = ExamTerm::where('tenant_id', $tenant->id)->latest()->value('id');
        $this->selectedSectionId = SchoolSection::where('tenant_id', $tenant->id)->value('id');
    }

    public function tabulateAndPublish(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (!$this->selectedTermId || !$this->selectedSectionId) {
            $this->errorMessage = 'Exam Term and Section are required.';
            return;
        }

        try {
            $term = ExamTerm::findOrFail($this->selectedTermId);
            $section = SchoolSection::findOrFail($this->selectedSectionId);
            $results = app(ResultTabulationService::class)->tabulateSectionResults($term, $section, true);

            $this->successMessage = "Tabulated and published results for {$results->count()} students!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function exportBroadsheetCsv(): StreamedResponse
    {
        $results = ($this->selectedTermId && $this->selectedSectionId)
            ? ExamResult::with('student')
                ->where('exam_term_id', $this->selectedTermId)
                ->where('school_section_id', $this->selectedSectionId)
                ->orderBy('section_rank')
                ->get()
            : collect();

        $filename = 'Exam_Broadsheet_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($results) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Rank', 'Admission #', 'Student Name', 'Max Marks', 'Obtained', 'Percentage', 'Grade', 'Status']);
            foreach ($results as $r) {
                fputcsv($handle, [
                    $r->section_rank,
                    $r->student->admission_number ?? '',
                    trim(($r->student->first_name ?? '') . ' ' . ($r->student->last_name ?? '')),
                    $r->total_max_marks,
                    $r->total_obtained_marks,
                    $r->percentage . '%',
                    $r->grade_letter,
                    strtoupper($r->result_status),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $terms = $tenant ? ExamTerm::where('tenant_id', $tenant->id)->latest()->get() : collect();
        $sections = $tenant ? SchoolSection::with('schoolClass')->where('tenant_id', $tenant->id)->get() : collect();

        $results = ($tenant && $this->selectedTermId && $this->selectedSectionId)
            ? ExamResult::with('student')
                ->where('tenant_id', $tenant->id)
                ->where('exam_term_id', $this->selectedTermId)
                ->where('school_section_id', $this->selectedSectionId)
                ->orderBy('section_rank')
                ->get()
            : collect();

        return view('livewire.exam.result-tabulation-portal', [
            'terms' => $terms,
            'sections' => $sections,
            'results' => $results,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
