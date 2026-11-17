<?php

namespace Tests\Feature\Exam;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\ExamManagementService;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Exam\Services\ResultTabulationService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MarksAndTabulationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class9;
    protected SchoolSection $section;
    protected Subject $math;
    protected Subject $english;
    protected Student $student1;
    protected Student $student2;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'City School',
            'slug' => 'city-exam-2',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model Town',
            'code' => 'CS-MT',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class9 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
            'display_order' => 9,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->class9->id,
            'name' => 'Iqbal',
            'capacity' => 30,
        ]);

        $this->math = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mathematics',
            'code' => 'MATH-9',
            'type' => 'theory',
        ]);

        $this->english = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'English',
            'code' => 'ENG-9',
            'type' => 'theory',
        ]);

        $this->student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'CS-01',
            'first_name' => 'Ali',
            'last_name' => 'Raza',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'CS-02',
            'first_name' => 'Hira',
            'last_name' => 'Saeed',
            'date_of_birth' => '2012-02-02',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        foreach ([$this->student1, $this->student2] as $stu) {
            SchoolStudentEnrollment::create([
                'tenant_id' => $this->tenant->id,
                'student_id' => $stu->id,
                'academic_session_id' => $this->session->id,
                'school_section_id' => $this->section->id,
                'enrolled_at' => '2026-04-01',
                'enrollment_status' => 'active',
            ]);
        }

        $this->teacher = User::factory()->create(['name' => 'Exam Controller']);
    }

    public function test_marks_entry_enforces_max_marks_and_tabulates_ranked_results(): void
    {
        $scale = (new GradingScaleService())->seedDefaultFbiseScale($this->tenant->id);
        $examService = new ExamManagementService();
        $tabService = new ResultTabulationService();

        $term = ExamTerm::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Final Term 2026',
            'term_type' => 'final',
            'weightage_percentage' => 100.00,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-15',
            'status' => 'marks_entry',
        ]);

        $mathPaper = ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $this->class9->id,
            'subject_id' => $this->math->id,
            'exam_date' => '2026-12-02',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);

        $engPaper = ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $this->class9->id,
            'subject_id' => $this->english->id,
            'exam_date' => '2026-12-04',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);

        // Reject out-of-bounds marks
        try {
            $examService->recordBulkMarks($mathPaper, $this->section->id, [
                $this->student1->id => ['marks' => 105.00],
            ]);
            $this->fail('Expected InvalidArgumentException when marks exceed max_marks.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('between 0 and 100', $e->getMessage());
        }

        // Record valid marks: Hira (95 + 90 = 185/200 = 92.5% A+), Ali (80 + 30 = 110/200 = 55% compartment)
        $examService->recordBulkMarks($mathPaper, $this->section->id, [
            $this->student1->id => ['marks' => 80.00],
            $this->student2->id => ['marks' => 95.00],
        ], true, $this->teacher);

        $examService->recordBulkMarks($engPaper, $this->section->id, [
            $this->student1->id => ['marks' => 30.00],
            $this->student2->id => ['marks' => 90.00],
        ], true, $this->teacher);

        $results = $tabService->tabulateSectionResults($term, $this->section, true);
        $this->assertCount(2, $results);

        $hiraResult = $results->firstWhere('student_id', $this->student2->id);
        $this->assertEquals(185.00, (float) $hiraResult->total_obtained_marks);
        $this->assertEquals(92.50, (float) $hiraResult->percentage);
        $this->assertEquals('A+', $hiraResult->grade_letter);
        $this->assertEquals(1, $hiraResult->section_rank);
        $this->assertEquals('pass', $hiraResult->result_status);

        $aliResult = $results->firstWhere('student_id', $this->student1->id);
        $this->assertEquals(110.00, (float) $aliResult->total_obtained_marks);
        $this->assertEquals(2, $aliResult->section_rank);
        $this->assertEquals('compartment', $aliResult->result_status);

        $term->refresh();
        $this->assertEquals('published', $term->status);
    }

    public function test_computes_weighted_cumulative_percentage_across_terms(): void
    {
        $scale = (new GradingScaleService())->seedDefaultFbiseScale($this->tenant->id);
        $examService = new ExamManagementService();
        $tabService = new ResultTabulationService();

        $midterm = ExamTerm::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Midterm',
            'term_type' => 'midterm',
            'weightage_percentage' => 30.00,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
        ]);

        $final = ExamTerm::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Final',
            'term_type' => 'final',
            'weightage_percentage' => 70.00,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-10',
        ]);

        $p1 = ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $midterm->id,
            'school_class_id' => $this->class9->id,
            'subject_id' => $this->math->id,
            'exam_date' => '2026-09-02',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);
        $p2 = ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $final->id,
            'school_class_id' => $this->class9->id,
            'subject_id' => $this->math->id,
            'exam_date' => '2026-12-02',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);

        $examService->recordBulkMarks($p1, $this->section->id, [$this->student1->id => ['marks' => 80.00]]);
        $examService->recordBulkMarks($p2, $this->section->id, [$this->student1->id => ['marks' => 90.00]]);

        $tabService->tabulateSectionResults($midterm, $this->section, true);
        $tabService->tabulateSectionResults($final, $this->section, true);

        // Weighted: (80 * 30 + 90 * 70) / 100 = (2400 + 6300) / 100 = 87.00%
        $agg = $tabService->computeWeightedCumulativePercentage($this->tenant->id, $this->session->id, $this->student1->id);
        $this->assertEquals(2, $agg['terms_count']);
        $this->assertEquals(87.00, $agg['cumulative_percentage']);
    }
}
