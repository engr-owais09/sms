<?php

namespace Tests\Feature\Exam;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Exam\Livewire\ExamScheduleManager;
use App\Modules\Exam\Livewire\MarksEntryConsole;
use App\Modules\Exam\Livewire\ResultTabulationPortal;
use App\Modules\Exam\Models\ExamMark;
use App\Modules\Exam\Models\ExamPaper;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Exam\Models\ExamTerm;
use App\Modules\Exam\Services\GradingScaleService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamLivewireComponentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class10;
    protected SchoolSection $section;
    protected Subject $subject;
    protected Student $student;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Allied School',
            'slug' => 'allied-exam-ui',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Johar Campus',
            'code' => 'AS-J',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        $this->user = User::factory()->create(['name' => 'Exam Admin']);
        $this->actingAs($this->user);

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

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->class10->id,
            'name' => 'Science',
            'capacity' => 30,
        ]);

        $this->subject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chemistry',
            'code' => 'CHEM-10',
            'type' => 'theory',
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'AS-101',
            'first_name' => 'Usman',
            'last_name' => 'Ghani',
            'date_of_birth' => '2011-05-05',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'enrolled_at' => '2026-04-01',
            'enrollment_status' => 'active',
        ]);
    }

    public function test_exam_livewire_workflow_creates_schedule_enters_marks_tabulates_and_renders_bilingual_report_card(): void
    {
        // 1. Schedule term and paper via ExamScheduleManager
        $sched = Livewire::test(ExamScheduleManager::class)
            ->set('termName', 'Annual Exam 2026')
            ->set('termType', 'annual')
            ->call('createTerm')
            ->assertSee('created successfully')
            ->set('paperClassId', $this->class10->id)
            ->set('paperSubjectId', $this->subject->id)
            ->set('maxMarks', 100.00)
            ->set('passingMarks', 40.00)
            ->call('addPaper')
            ->assertSee('Exam paper scheduled successfully');

        $term = ExamTerm::firstOrFail();
        $paper = ExamPaper::firstOrFail();

        // 2. Enter marks via MarksEntryConsole
        Livewire::test(MarksEntryConsole::class)
            ->set('selectedTermId', $term->id)
            ->set('selectedPaperId', $paper->id)
            ->set('selectedSectionId', $this->section->id)
            ->call('loadStudents')
            ->assertCount('markRows', 1)
            ->set('markRows.0.marks', 88.00)
            ->call('saveMarks')
            ->assertSee('Marks saved and submitted successfully');

        // 3. Tabulate & export broadsheet via ResultTabulationPortal
        $portal = Livewire::test(ResultTabulationPortal::class)
            ->set('selectedTermId', $term->id)
            ->set('selectedSectionId', $this->section->id)
            ->call('tabulateAndPublish')
            ->assertSee('Tabulated and published results for 1 students')
            ->assertSee('88.00%');

        $portal->call('exportBroadsheetCsv')->assertFileDownloaded();

        // 4. Render bilingual report card in English LTR and Urdu RTL
        $result = ExamResult::with(['student', 'section.schoolClass', 'examTerm'])->firstOrFail();
        $marks = ExamMark::with('paper.subject')->where('student_id', $this->student->id)->get();

        $enHtml = view('reports.bilingual-report-card', [
            'tenant' => $this->tenant,
            'result' => $result,
            'marks' => $marks,
            'isRtl' => false,
        ])->render();
        $this->assertStringContainsString('dir="ltr"', $enHtml);
        $this->assertStringContainsString('Usman Ghani', $enHtml);

        $urHtml = view('reports.bilingual-report-card', [
            'tenant' => $this->tenant,
            'result' => $result,
            'marks' => $marks,
            'isRtl' => true,
        ])->render();
        $this->assertStringContainsString('dir="rtl"', $urHtml);
        $this->assertStringContainsString('تعلیمی رزلٹ کارڈ', $urHtml);
    }
}
