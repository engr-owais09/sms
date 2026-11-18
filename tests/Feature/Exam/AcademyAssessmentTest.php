<?php

namespace Tests\Feature\Exam;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Exam\Models\AcademyAssessment;
use App\Modules\Exam\Services\AcademyAssessmentService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_and_rank_academy_mock_exam_scores(): void
    {
        $tenant = Tenant::create([
            'name' => 'KIPS Academy',
            'slug' => 'kips-exam-3',
            'type' => 'academy',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'KIPS-M',
            'city' => 'Lahore',
        ]);

        app(TenantContext::class)->setTenant($tenant);
        app(TenantContext::class)->setBranch($branch);

        $program = AcademyProgram::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'MDCAT',
            'code' => 'MDCAT',
        ]);

        $course = AcademyCourse::create([
            'tenant_id' => $tenant->id,
            'academy_program_id' => $program->id,
            'name' => 'Biology',
            'code' => 'BIO-M',
            'duration_weeks' => 12,
            'base_fee' => 18000.00,
        ]);

        $batch = AcademyBatch::create([
            'tenant_id' => $tenant->id,
            'academy_course_id' => $course->id,
            'branch_id' => $branch->id,
            'name' => 'Morning A1',
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-01',
            'max_capacity' => 50,
            'status' => 'in_progress',
        ]);

        $s1 = Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => 'K-01',
            'first_name' => 'Zoya',
            'last_name' => 'Khan',
            'date_of_birth' => '2007-04-12',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $s2 = Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => 'K-02',
            'first_name' => 'Saad',
            'last_name' => 'Tariq',
            'date_of_birth' => '2007-06-22',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $assessment = AcademyAssessment::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academy_batch_id' => $batch->id,
            'title' => 'Mock Test #1 - Cell Biology',
            'assessment_type' => 'mock_exam',
            'assessment_date' => '2026-10-15',
            'max_marks' => 200.00,
            'passing_marks' => 100.00,
        ]);

        $scores = (new AcademyAssessmentService())->recordAndRankScores($assessment, [
            $s1->id => ['marks' => 180.00],
            $s2->id => ['marks' => 150.00],
        ], true);

        $this->assertCount(2, $scores);
        $zoya = $scores->firstWhere('student_id', $s1->id);
        $this->assertEquals(90.00, (float) $zoya->percentage);
        $this->assertEquals(1, $zoya->rank);

        $assessment->refresh();
        $this->assertEquals('published', $assessment->status);
    }
}
