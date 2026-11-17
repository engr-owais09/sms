<?php

namespace Tests\Feature\Exam;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingAndExamSetupTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class10;
    protected Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Fauji Foundation School',
            'slug' => 'ffs-exam-1',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rawalpindi Main',
            'code' => 'FFS-RWP',
            'city' => 'Rawalpindi',
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

        $this->class10 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 10',
            'numeric_level' => 10,
            'display_order' => 10,
        ]);

        $this->math = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mathematics',
            'code' => 'MATH-10',
            'type' => 'theory',
        ]);
    }

    public function test_can_seed_fbise_grading_scale_and_resolve_grades(): void
    {
        $service = new GradingScaleService();
        $scale = $service->seedDefaultFbiseScale($this->tenant->id);

        $this->assertCount(7, $scale->bands);

        $top = $service->resolveGrade($scale, 94.50);
        $this->assertEquals('A+', $top['grade_letter']);
        $this->assertTrue($top['is_passing']);
        $this->assertEquals(4.00, $top['gpa_points']);

        $fail = $service->resolveGrade($scale, 34.00);
        $this->assertEquals('F', $fail['grade_letter']);
        $this->assertFalse($fail['is_passing']);
    }

    public function test_can_create_exam_term_and_date_sheet_papers_with_marks_bounds(): void
    {
        $scale = (new GradingScaleService())->seedDefaultFbiseScale($this->tenant->id);

        $term = ExamTerm::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'grading_scale_id' => $scale->id,
            'name' => 'Midterm Examination 2026',
            'term_type' => 'midterm',
            'weightage_percentage' => 40.00,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-20',
            'status' => 'scheduled',
        ]);

        $paper = ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $this->class10->id,
            'subject_id' => $this->math->id,
            'exam_date' => '2026-10-12',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'passing_marks' => 40.00,
        ]);

        $this->assertDatabaseHas('exam_papers', ['id' => $paper->id, 'max_marks' => 100.00]);

        // Passing marks cannot exceed max_marks
        $this->expectException(QueryException::class);
        ExamPaper::create([
            'tenant_id' => $this->tenant->id,
            'exam_term_id' => $term->id,
            'school_class_id' => $this->class10->id,
            'subject_id' => Subject::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Physics',
                'code' => 'PHY-10',
                'type' => 'theory',
            ])->id,
            'exam_date' => '2026-10-14',
            'max_marks' => 50.00,
            'passing_marks' => 60.00,
        ]);
    }
}
